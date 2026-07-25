<?php

namespace Tests\Feature;

use App\Features\CoinSeller\Models\CoinRechargeCode;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\CoinSeller\Models\CoinSellerSale;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoinSellerSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_user_can_apply_to_become_coin_seller(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/coin-sellers/apply', [
            'display_name' => 'Civin Official Coins',
            'contact_info' => 'seller@civin.test',
            'application_message' => 'I want to sell coins offline.',
        ])->assertCreated()
            ->assertJsonPath('data.status', CoinSeller::STATUS_PENDING)
            ->assertJsonPath('data.display_name', 'Civin Official Coins');

        $this->assertDatabaseHas('coin_sellers', [
            'user_id' => $user->id,
            'status' => CoinSeller::STATUS_PENDING,
        ]);
    }

    public function test_admin_can_approve_allocate_and_seller_can_direct_sell(): void
    {
        Event::fake([WalletUpdated::class]);

        $sellerUser = User::factory()->create();
        $buyer = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        Sanctum::actingAs($sellerUser);
        $sellerId = $this->postJson('/api/v1/coin-sellers/apply', [
            'display_name' => 'Agent One',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/coin-sellers/{$sellerId}", [
            'status' => CoinSeller::STATUS_ACTIVE,
            'commission_rate' => 10,
        ])->assertOk()
            ->assertJsonPath('data.status', CoinSeller::STATUS_ACTIVE);

        $this->postJson("/api/v1/admin/coin-sellers/{$sellerId}/inventory", [
            'coins' => 5000,
            'notes' => 'Initial stock',
        ])->assertOk()
            ->assertJsonPath('data.inventory_balance', 5000);

        Sanctum::actingAs($sellerUser);
        $this->postJson('/api/v1/coin-sellers/sell', [
            'buyer_id' => $buyer->id,
            'coins' => 1000,
            'price_amount' => 900,
            'currency' => 'USD',
        ])->assertCreated()
            ->assertJsonPath('data.type', CoinSellerSale::TYPE_DIRECT_TRANSFER)
            ->assertJsonPath('data.coins', 1000)
            ->assertJsonPath('data.commission_amount', 100);

        $this->assertSame(1000, $buyer->fresh()->wallet->coins_balance);
        $this->assertSame(4000, CoinSeller::query()->findOrFail($sellerId)->inventory_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $buyer->id,
            'type' => WalletTransaction::TYPE_COIN_SELLER_PURCHASE,
            'amount' => 1000,
        ]);
        Event::assertDispatched(WalletUpdated::class);
    }

    public function test_seller_can_create_code_and_buyer_can_redeem(): void
    {
        Event::fake([WalletUpdated::class]);

        $sellerUser = User::factory()->create();
        $buyer = User::factory()->create();
        $seller = CoinSeller::factory()->active(2000)->create([
            'user_id' => $sellerUser->id,
            'commission_rate' => 5,
        ]);

        Sanctum::actingAs($sellerUser);
        $code = $this->postJson('/api/v1/coin-sellers/codes', [
            'coins' => 500,
            'expires_in_hours' => 24,
        ])->assertCreated()
            ->assertJsonPath('data.coins', 500)
            ->assertJsonPath('data.status', CoinRechargeCode::STATUS_AVAILABLE)
            ->json('data.code');

        $this->assertSame(1500, $seller->fresh()->inventory_balance);

        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/coin-sellers/redeem', [
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('data.type', CoinSellerSale::TYPE_RECHARGE_CODE)
            ->assertJsonPath('data.coins', 500);

        $this->assertSame(500, $buyer->fresh()->wallet->coins_balance);
        $this->assertDatabaseHas('coin_recharge_codes', [
            'code' => $code,
            'status' => CoinRechargeCode::STATUS_REDEEMED,
            'redeemed_by' => $buyer->id,
        ]);
    }

    public function test_seller_dashboard_returns_stats(): void
    {
        $sellerUser = User::factory()->create();
        CoinSeller::factory()->active(3000)->create([
            'user_id' => $sellerUser->id,
            'total_sold' => 1200,
            'total_commission' => 60,
            'sales_count' => 2,
        ]);

        Sanctum::actingAs($sellerUser);
        $this->getJson('/api/v1/coin-sellers/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.inventory_balance', 3000)
            ->assertJsonPath('data.stats.total_sold', 1200)
            ->assertJsonPath('data.stats.sales_count', 2);
    }

    public function test_non_admin_cannot_approve_sellers(): void
    {
        $user = User::factory()->create();
        $seller = CoinSeller::factory()->create();

        Sanctum::actingAs($user);
        $this->patchJson("/api/v1/admin/coin-sellers/{$seller->id}", [
            'status' => CoinSeller::STATUS_ACTIVE,
        ])->assertForbidden();
    }
}
