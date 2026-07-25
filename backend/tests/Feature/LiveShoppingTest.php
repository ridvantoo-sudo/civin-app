<?php

namespace Tests\Feature;

use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveShoppingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_host_can_manage_products_pin_and_viewer_can_checkout_with_wallet(): void
    {
        $host = User::factory()->create(['is_guest' => false, 'status' => 'active']);
        $viewer = User::factory()->create(['is_guest' => false, 'status' => 'active']);
        Wallet::query()->updateOrCreate(
            ['user_id' => $viewer->id],
            ['coins_balance' => 1000, 'diamonds_balance' => 0],
        );

        $room = LiveRoom::factory()->create([
            'host_id' => $host->id,
            'status' => 'live',
        ]);

        Sanctum::actingAs($host);
        $productId = $this->postJson('/api/v1/live-shopping/products', [
            'name' => 'Live Hoodie',
            'description' => 'Limited drop',
            'price_coins' => 150,
            'stock' => 5,
            'is_offline_available' => true,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Live Hoodie')
            ->json('data.id');

        $pinId = $this->postJson("/api/v1/live/{$room->id}/shopping/pins", [
            'product_id' => $productId,
            'flash_deal_price' => 100,
        ])->assertCreated()
            ->assertJsonPath('data.has_flash_deal', true)
            ->assertJsonPath('data.effective_price', 100)
            ->json('data.id');

        $this->getJson("/api/v1/live/{$room->id}/shopping/pins")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($viewer);
        $this->postJson('/api/v1/live-shopping/cart/items', [
            'product_id' => $productId,
            'quantity' => 1,
            'live_pin_id' => $pinId,
            'room_id' => $room->id,
        ])->assertCreated()
            ->assertJsonPath('data.item_count', 1)
            ->assertJsonPath('data.total_coins', 100);

        $orderId = $this->postJson('/api/v1/live-shopping/checkout', [
            'payment_method' => 'wallet',
            'room_id' => $room->id,
            'shipping_name' => 'Viewer One',
            'shipping_city' => 'Erbil',
            'shipping_country' => 'IQ',
        ])->assertCreated()
            ->assertJsonPath('data.payment_status', ShopOrder::PAYMENT_PAID)
            ->assertJsonPath('data.status', ShopOrder::STATUS_PROCESSING)
            ->assertJsonPath('data.total_coins', 100)
            ->json('data.id');

        $this->assertSame(900, (int) $viewer->fresh()->wallet->coins_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $viewer->id,
            'type' => WalletTransaction::TYPE_LIVE_SHOPPING_PURCHASE,
            'amount' => -100,
        ]);

        Sanctum::actingAs($host);
        $this->patchJson("/api/v1/live-shopping/orders/{$orderId}/shipping", [
            'status' => ShopOrder::STATUS_SHIPPED,
            'tracking_number' => 'TRK-001',
        ])->assertOk()
            ->assertJsonPath('data.status', ShopOrder::STATUS_SHIPPED)
            ->assertJsonPath('data.shipping.tracking_number', 'TRK-001');

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/live-shopping/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.status', ShopOrder::STATUS_SHIPPED);
    }

    public function test_offline_store_lists_active_products(): void
    {
        $seller = User::factory()->create(['is_guest' => false, 'status' => 'active']);
        Sanctum::actingAs($seller);

        $this->postJson('/api/v1/live-shopping/products', [
            'name' => 'Offline Cap',
            'price_coins' => 50,
            'stock' => 3,
            'is_offline_available' => true,
        ])->assertCreated();

        $this->getJson("/api/v1/live-shopping/sellers/{$seller->id}/products")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Offline Cap');
    }
}
