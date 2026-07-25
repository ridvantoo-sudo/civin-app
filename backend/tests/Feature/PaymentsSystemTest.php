<?php

namespace Tests\Feature;

use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\Payments\Models\Invoice;
use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Payments\Models\RevenueShare;
use App\Features\Payments\Services\RevenueSharingService;
use App\Features\Payments\Services\TaxService;
use App\Features\Payments\Support\PaymentStatus;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentsSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        config()->set('payments.gateways.stripe', [
            'enabled' => true,
            'mode' => 'sandbox',
            'secret_key' => 'sk_test_123',
            'publishable_key' => 'pk_test_123',
            'webhook_secret' => 'whsec_test',
            'base_url' => 'https://api.stripe.com/v1',
        ]);
        config()->set('payments.default_currency', 'USD');
        config()->set('payments.revenue_share.platform_bps', 2000);
        config()->set('payments.revenue_share.agency_bps', 500);
    }

    private function order(User $buyer, User $seller, int $coins = 1000): ShopOrder
    {
        return ShopOrder::query()->create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD'.strtoupper(Str::random(8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => ShopOrder::STATUS_PENDING,
            'payment_status' => ShopOrder::PAYMENT_PENDING,
            'payment_method' => ShopOrder::METHOD_EXTERNAL,
            'total_coins' => $coins,
        ]);
    }

    public function test_payment_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/payments')->assertUnauthorized();
        $this->postJson('/api/v1/payments/intents', [])->assertUnauthorized();
        $this->getJson('/api/v1/invoices')->assertUnauthorized();
    }

    public function test_available_gateways_lists_enabled_only(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/payments/gateways')->assertOk();
        $keys = collect($response->json('data'))->pluck('gateway')->all();

        $this->assertContains('stripe', $keys);
        $this->assertNotContains('paypal', $keys); // disabled by default
    }

    public function test_create_stripe_intent_for_merchant_order(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_test_123',
                'client_secret' => 'pi_test_123_secret',
                'status' => 'requires_payment_method',
                'amount' => 1000,
                'currency' => 'usd',
            ], 200),
        ]);

        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $order = $this->order($buyer, $seller);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/payments/intents', [
            'gateway' => 'stripe',
            'payable_type' => ShopOrder::class,
            'payable_id' => $order->id,
        ])->assertCreated();

        $this->assertSame('stripe', $response->json('data.gateway'));
        $this->assertSame(PaymentStatus::REQUIRES_ACTION, $response->json('data.status'));
        $this->assertSame('pi_test_123_secret', $response->json('data.client_secret'));
        $this->assertSame(1000, $response->json('data.amount'));

        $this->assertDatabaseHas('payment_transactions', [
            'provider_payment_id' => 'pi_test_123',
            'status' => PaymentStatus::REQUIRES_ACTION,
            'payable_id' => $order->id,
        ]);
    }

    public function test_virtual_goods_are_blocked_by_store_policy(): void
    {
        Sanctum::actingAs($buyer = User::factory()->create());

        $this->postJson('/api/v1/payments/intents', [
            'gateway' => 'stripe',
            'payable_type' => User::class, // not in allow-list
            'payable_id' => $buyer->id,
        ])->assertStatus(422)->assertJsonValidationErrorFor('payable_type');
    }

    public function test_stripe_webhook_settles_order_revenue_and_invoice(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_test_123',
                'client_secret' => 'pi_test_123_secret',
                'status' => 'requires_payment_method',
            ], 200),
        ]);

        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $order = $this->order($buyer, $seller, 1000);

        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/payments/intents', [
            'gateway' => 'stripe',
            'payable_type' => ShopOrder::class,
            'payable_id' => $order->id,
        ])->assertCreated();

        $transaction = PaymentTransaction::query()->firstOrFail();

        // Build a signed Stripe webhook.
        $event = [
            'id' => 'evt_test_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_test_123',
                'status' => 'succeeded',
                'amount' => 1000,
                'currency' => 'usd',
                'metadata' => ['transaction_id' => $transaction->id],
            ]],
        ];
        $payload = json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $this->call(
            'POST',
            '/api/v1/payments/webhooks/stripe',
            [], [], [],
            [
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload,
        )->assertOk();

        $transaction->refresh();
        $this->assertSame(PaymentStatus::SUCCEEDED, $transaction->status);
        $this->assertNotNull($transaction->paid_at);

        // Order marked paid.
        $order->refresh();
        $this->assertTrue($order->isPaid());

        // Revenue split: platform (2000bps=200) + merchant (remainder=800).
        $shares = RevenueShare::query()->where('payment_transaction_id', $transaction->id)->get();
        $this->assertSame(2, $shares->count());
        $this->assertSame(200, (int) $shares->firstWhere('beneficiary_type', 'platform')->amount);
        $this->assertSame(800, (int) $shares->firstWhere('beneficiary_type', 'merchant')->amount);

        // Invoice generated.
        $this->assertDatabaseHas('invoices', [
            'payment_transaction_id' => $transaction->id,
            'total_amount' => 1000,
            'status' => Invoice::STATUS_PAID,
        ]);
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $this->call(
            'POST',
            '/api/v1/payments/webhooks/stripe',
            [], [], [],
            ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=deadbeef', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode(['id' => 'evt', 'type' => 'payment_intent.succeeded', 'data' => ['object' => []]]),
        )->assertStatus(400);
    }

    public function test_tax_service_computes_exclusive_and_inclusive(): void
    {
        $tax = new TaxService;

        $exclusive = $tax->compute(1000, 500, false); // 5%
        $this->assertSame(['subtotal' => 1000, 'tax' => 50, 'total' => 1050, 'rate_bps' => 500], $exclusive);

        $inclusive = $tax->compute(1050, 500, true);
        $this->assertSame(1000, $inclusive['subtotal']);
        $this->assertSame(50, $inclusive['tax']);
    }

    public function test_revenue_rule_defaults(): void
    {
        $service = new RevenueSharingService;
        $rule = $service->resolveRule((string) Str::uuid());

        $this->assertSame(2000, $rule['platform_bps']);
        $this->assertSame(500, $rule['agency_bps']);
    }
}
