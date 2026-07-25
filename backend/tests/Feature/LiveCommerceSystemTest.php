<?php

namespace Tests\Feature;

use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Models\StoreCoupon;
use App\Features\LiveShopping\Models\StoreProduct;
use App\Features\LiveShopping\Support\StoreCouponType;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveCommerceSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_live_commerce_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/live-shopping/coupons')->assertUnauthorized();
        $this->getJson('/api/v1/live-shopping/seller/analytics/overview')->assertUnauthorized();
        $this->postJson('/api/v1/live-shopping/coupons/validate', [])->assertUnauthorized();
    }

    public function test_seller_can_create_coupon_and_buyer_checkout_applies_discount(): void
    {
        $seller = User::factory()->create(['is_guest' => false, 'status' => 'active']);
        $buyer = User::factory()->create(['is_guest' => false, 'status' => 'active']);
        Wallet::query()->updateOrCreate(
            ['user_id' => $buyer->id],
            ['coins_balance' => 1000, 'diamonds_balance' => 0],
        );

        Sanctum::actingAs($seller);
        $productId = $this->postJson('/api/v1/live-shopping/products', [
            'name' => 'Tee',
            'price_coins' => 200,
            'stock' => 10,
            'is_offline_available' => true,
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/live-shopping/coupons', [
            'code' => 'SAVE20',
            'type' => StoreCouponType::PERCENT,
            'value' => 20,
            'min_order_coins' => 100,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'SAVE20');

        Sanctum::actingAs($buyer);
        $preview = $this->postJson('/api/v1/live-shopping/coupons/validate', [
            'code' => 'save20',
            'seller_id' => $seller->id,
            'subtotal_coins' => 200,
        ])->assertOk()
            ->assertJsonPath('data.discount_coins', 40)
            ->assertJsonPath('data.payable_coins', 160)
            ->json('data');

        $this->assertSame(40, $preview['discount_coins']);

        $this->postJson('/api/v1/live-shopping/cart/items', [
            'product_id' => $productId,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson('/api/v1/live-shopping/checkout', [
            'payment_method' => 'wallet',
            'coupon_code' => 'SAVE20',
        ])->assertCreated()
            ->assertJsonPath('data.subtotal_coins', 200)
            ->assertJsonPath('data.discount_coins', 40)
            ->assertJsonPath('data.total_coins', 160)
            ->assertJsonPath('data.coupon_code', 'SAVE20')
            ->assertJsonPath('data.payment_status', ShopOrder::PAYMENT_PAID);

        $this->assertSame(840, (int) $buyer->fresh()->wallet->coins_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $buyer->id,
            'type' => WalletTransaction::TYPE_LIVE_SHOPPING_PURCHASE,
            'amount' => -160,
        ]);
        $this->assertDatabaseHas('store_coupon_redemptions', [
            'user_id' => $buyer->id,
            'discount_coins' => 40,
        ]);
        $this->assertSame(1, (int) StoreCoupon::query()->where('code', 'SAVE20')->value('redemptions_count'));
    }

    public function test_fixed_coupon_and_invalid_code_rejected(): void
    {
        $seller = User::factory()->create(['is_guest' => false, 'status' => 'active']);
        $buyer = User::factory()->create(['is_guest' => false, 'status' => 'active']);
        Wallet::query()->updateOrCreate(
            ['user_id' => $buyer->id],
            ['coins_balance' => 500, 'diamonds_balance' => 0],
        );

        $product = StoreProduct::query()->create([
            'seller_id' => $seller->id,
            'name' => 'Hat',
            'price_coins' => 150,
            'stock' => 5,
            'status' => StoreProduct::STATUS_ACTIVE,
            'is_offline_available' => true,
            'sort_order' => 0,
        ]);

        StoreCoupon::query()->create([
            'seller_id' => $seller->id,
            'code' => 'FLAT50',
            'type' => StoreCouponType::FIXED_COINS,
            'value' => 50,
            'active' => true,
            'per_user_limit' => 1,
        ]);

        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/live-shopping/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson('/api/v1/live-shopping/checkout', [
            'payment_method' => 'wallet',
            'coupon_code' => 'NOPE',
        ])->assertStatus(422);

        $this->postJson('/api/v1/live-shopping/checkout', [
            'payment_method' => 'wallet',
            'coupon_code' => 'FLAT50',
        ])->assertCreated()
            ->assertJsonPath('data.total_coins', 100)
            ->assertJsonPath('data.discount_coins', 50);
    }

    public function test_merchant_analytics_overview_and_products(): void
    {
        $seller = User::factory()->create(['is_guest' => false, 'status' => 'active']);
        $buyer = User::factory()->create(['is_guest' => false, 'status' => 'active']);
        Wallet::query()->updateOrCreate(
            ['user_id' => $buyer->id],
            ['coins_balance' => 2000, 'diamonds_balance' => 0],
        );

        Sanctum::actingAs($seller);
        $productId = $this->postJson('/api/v1/live-shopping/products', [
            'name' => 'Mug',
            'price_coins' => 100,
            'stock' => 20,
            'is_offline_available' => true,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/live-shopping/cart/items', [
            'product_id' => $productId,
            'quantity' => 2,
        ])->assertCreated();
        $this->postJson('/api/v1/live-shopping/checkout', [
            'payment_method' => 'wallet',
        ])->assertCreated();

        Sanctum::actingAs($seller);
        $this->getJson('/api/v1/live-shopping/seller/analytics/overview')
            ->assertOk()
            ->assertJsonPath('data.paid_orders', 1)
            ->assertJsonPath('data.revenue_coins', 200);

        $products = $this->getJson('/api/v1/live-shopping/seller/analytics/products')
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($products);
        $this->assertSame(2, $products[0]['units_sold']);
        $this->assertSame(200, $products[0]['revenue_coins']);

        $this->getJson('/api/v1/live-shopping/seller/analytics/timeseries')
            ->assertOk()
            ->assertJsonStructure(['data' => [['date', 'orders', 'revenue_coins']]]);
    }

    public function test_seller_can_deactivate_coupon(): void
    {
        $seller = User::factory()->create(['is_guest' => false, 'status' => 'active']);
        Sanctum::actingAs($seller);

        $id = $this->postJson('/api/v1/live-shopping/coupons', [
            'code' => 'TEMP10',
            'type' => StoreCouponType::FIXED_COINS,
            'value' => 10,
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/live-shopping/coupons/{$id}")
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }
}
