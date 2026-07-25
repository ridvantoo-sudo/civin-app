<?php

namespace Tests\Feature;

use App\Features\Marketplace\Models\MarketItem;
use App\Features\Marketplace\Models\MarketPurchase;
use App\Features\Marketplace\Models\StickerPackItem;
use App\Features\Marketplace\Models\UserMarketItem;
use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_marketplace_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/marketplace/items')->assertUnauthorized();
        $this->postJson('/api/v1/marketplace/purchase', [])->assertUnauthorized();
    }

    public function test_catalog_includes_profile_themes_and_sticker_packs(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $theme = MarketItem::factory()->create([
            'type' => MarketItem::TYPE_PROFILE_THEME,
            'name' => 'Test Theme',
            'coin_price' => 100,
            'status' => MarketItem::STATUS_ACTIVE,
        ]);
        $pack = MarketItem::factory()->create([
            'type' => MarketItem::TYPE_STICKER_PACK,
            'name' => 'Test Pack',
            'coin_price' => 200,
            'status' => MarketItem::STATUS_ACTIVE,
        ]);

        $themeIds = collect($this->getJson('/api/v1/marketplace/items?type=profile_theme')
            ->assertOk()
            ->json('data'))->pluck('id');
        $this->assertTrue($themeIds->contains($theme->id));

        $packIds = collect($this->getJson('/api/v1/marketplace/items?type=sticker_pack')
            ->assertOk()
            ->json('data'))->pluck('id');
        $this->assertTrue($packIds->contains($pack->id));
    }

    public function test_purchase_profile_theme_debits_wallet_and_auto_equips(): void
    {
        $user = $this->userWithCoins(1000);
        $theme = MarketItem::factory()->create([
            'type' => MarketItem::TYPE_PROFILE_THEME,
            'name' => 'Ocean Theme',
            'coin_price' => 250,
            'duration_days' => null,
            'status' => MarketItem::STATUS_ACTIVE,
            'metadata' => ['colors' => ['accent' => '#00A']],
        ]);

        Sanctum::actingAs($user);
        $inventoryId = $this->postJson('/api/v1/marketplace/purchase', [
            'market_item_id' => $theme->id,
        ])->assertCreated()
            ->assertJsonPath('data.is_equipped', true)
            ->assertJsonPath('data.item.type', MarketItem::TYPE_PROFILE_THEME)
            ->json('data.id');

        $this->assertSame(750, (int) $user->fresh()->wallet->coins_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => MarketPurchase::WALLET_TYPE_MARKETPLACE_PURCHASE,
            'amount' => -250,
        ]);
        $this->assertDatabaseHas('user_market_items', [
            'id' => $inventoryId,
            'is_equipped' => true,
            'status' => UserMarketItem::STATUS_OWNED,
        ]);
    }

    public function test_purchase_sticker_pack_does_not_auto_equip_by_default(): void
    {
        config()->set('marketplace.sticker_packs.auto_equip_on_purchase', false);

        $user = $this->userWithCoins(500);
        $pack = MarketItem::factory()->create([
            'type' => MarketItem::TYPE_STICKER_PACK,
            'name' => 'Wave Pack',
            'coin_price' => 300,
            'status' => MarketItem::STATUS_ACTIVE,
        ]);
        StickerPackItem::query()->create([
            'market_item_id' => $pack->id,
            'code' => 'wave',
            'label' => 'Wave',
            'asset_url' => 'https://cdn.civin.app/s/wave.webp',
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/marketplace/purchase', [
            'market_item_id' => $pack->id,
        ])->assertCreated()
            ->assertJsonPath('data.is_equipped', false)
            ->assertJsonPath('data.item.type', MarketItem::TYPE_STICKER_PACK);

        $this->assertSame(200, (int) $user->fresh()->wallet->coins_balance);

        $stickers = $this->getJson("/api/v1/marketplace/items/{$pack->id}/stickers")
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $stickers);
        $this->assertSame('wave', $stickers[0]['code']);
    }

    public function test_stickers_endpoint_rejects_non_pack_items(): void
    {
        $user = User::factory()->create();
        $frame = MarketItem::factory()->create([
            'type' => MarketItem::TYPE_FRAME,
            'status' => MarketItem::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($user);
        $this->getJson("/api/v1/marketplace/items/{$frame->id}/stickers")->assertStatus(422);
    }

    public function test_insufficient_coins_blocks_theme_purchase(): void
    {
        $user = $this->userWithCoins(50);
        $theme = MarketItem::factory()->create([
            'type' => MarketItem::TYPE_PROFILE_THEME,
            'coin_price' => 700,
            'status' => MarketItem::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/marketplace/purchase', [
            'market_item_id' => $theme->id,
        ])->assertStatus(422);
    }

    private function userWithCoins(int $coins): User
    {
        $user = User::factory()->withCoins($coins)->create();
        Profile::factory()->create([
            'user_id' => $user->id,
            'display_name' => $user->username,
        ]);

        return $user->fresh(['profile', 'wallet']);
    }
}
