<?php

use App\Features\Marketplace\Models\MarketItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sticker_pack_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('market_item_id')->constrained('market_items')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('label', 120);
            $table->string('asset_url', 2048);
            $table->string('preview_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['market_item_id', 'code']);
            $table->index(['market_item_id', 'sort_order']);
        });

        $this->seedThemesAndPacks();
    }

    private function seedThemesAndPacks(): void
    {
        $now = now();

        $midnightId = (string) Str::uuid();
        DB::table('market_items')->insert([
            'id' => $midnightId,
            'type' => MarketItem::TYPE_PROFILE_THEME,
            'name' => 'Midnight Profile',
            'description' => 'Deep navy profile theme with cool accents.',
            'icon_url' => 'https://cdn.civin.app/marketplace/themes/midnight.png',
            'asset_url' => 'https://cdn.civin.app/marketplace/themes/midnight.json',
            'coin_price' => 700,
            'duration_days' => null,
            'is_limited' => false,
            'stock_total' => null,
            'stock_remaining' => null,
            'special_value' => null,
            'membership_required' => null,
            'status' => MarketItem::STATUS_ACTIVE,
            'sort_order' => 1,
            'starts_at' => null,
            'ends_at' => null,
            'metadata' => json_encode([
                'colors' => [
                    'background' => '#0B1220',
                    'surface' => '#152238',
                    'accent' => '#5B8CFF',
                    'text' => '#E8EEF9',
                ],
                'preview_url' => 'https://cdn.civin.app/marketplace/themes/midnight-preview.png',
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sunsetId = (string) Str::uuid();
        DB::table('market_items')->insert([
            'id' => $sunsetId,
            'type' => MarketItem::TYPE_PROFILE_THEME,
            'name' => 'Sunset Glow',
            'description' => 'Warm orange-to-rose profile theme.',
            'icon_url' => 'https://cdn.civin.app/marketplace/themes/sunset.png',
            'asset_url' => 'https://cdn.civin.app/marketplace/themes/sunset.json',
            'coin_price' => 950,
            'duration_days' => 30,
            'is_limited' => false,
            'stock_total' => null,
            'stock_remaining' => null,
            'special_value' => null,
            'membership_required' => null,
            'status' => MarketItem::STATUS_ACTIVE,
            'sort_order' => 2,
            'starts_at' => null,
            'ends_at' => null,
            'metadata' => json_encode([
                'colors' => [
                    'background' => '#2A1210',
                    'surface' => '#3D1C18',
                    'accent' => '#FF8A4C',
                    'text' => '#FFF1E6',
                ],
                'preview_url' => 'https://cdn.civin.app/marketplace/themes/sunset-preview.png',
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $wavePackId = (string) Str::uuid();
        DB::table('market_items')->insert([
            'id' => $wavePackId,
            'type' => MarketItem::TYPE_STICKER_PACK,
            'name' => 'Civin Waves',
            'description' => 'Friendly wave and reaction stickers.',
            'icon_url' => 'https://cdn.civin.app/marketplace/stickers/waves/pack.png',
            'asset_url' => null,
            'coin_price' => 300,
            'duration_days' => null,
            'is_limited' => false,
            'stock_total' => null,
            'stock_remaining' => null,
            'special_value' => null,
            'membership_required' => null,
            'status' => MarketItem::STATUS_ACTIVE,
            'sort_order' => 1,
            'starts_at' => null,
            'ends_at' => null,
            'metadata' => json_encode(['pack_code' => 'civin_waves'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $partyPackId = (string) Str::uuid();
        DB::table('market_items')->insert([
            'id' => $partyPackId,
            'type' => MarketItem::TYPE_STICKER_PACK,
            'name' => 'Party Pop',
            'description' => 'Celebration stickers for live chat.',
            'icon_url' => 'https://cdn.civin.app/marketplace/stickers/party/pack.png',
            'asset_url' => null,
            'coin_price' => 450,
            'duration_days' => null,
            'is_limited' => true,
            'stock_total' => 500,
            'stock_remaining' => 500,
            'special_value' => null,
            'membership_required' => 'vip',
            'status' => MarketItem::STATUS_ACTIVE,
            'sort_order' => 2,
            'starts_at' => null,
            'ends_at' => null,
            'metadata' => json_encode(['pack_code' => 'party_pop'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stickers = [
            [$wavePackId, 'wave', 'Wave', 'https://cdn.civin.app/marketplace/stickers/waves/wave.webp', 1],
            [$wavePackId, 'hi', 'Hi', 'https://cdn.civin.app/marketplace/stickers/waves/hi.webp', 2],
            [$wavePackId, 'clap', 'Clap', 'https://cdn.civin.app/marketplace/stickers/waves/clap.webp', 3],
            [$wavePackId, 'heart', 'Heart', 'https://cdn.civin.app/marketplace/stickers/waves/heart.webp', 4],
            [$partyPackId, 'confetti', 'Confetti', 'https://cdn.civin.app/marketplace/stickers/party/confetti.webp', 1],
            [$partyPackId, 'cake', 'Cake', 'https://cdn.civin.app/marketplace/stickers/party/cake.webp', 2],
            [$partyPackId, 'fireworks', 'Fireworks', 'https://cdn.civin.app/marketplace/stickers/party/fireworks.webp', 3],
        ];

        foreach ($stickers as [$packId, $code, $label, $asset, $sort]) {
            DB::table('sticker_pack_items')->insert([
                'id' => (string) Str::uuid(),
                'market_item_id' => $packId,
                'code' => $code,
                'label' => $label,
                'asset_url' => $asset,
                'preview_url' => $asset,
                'sort_order' => $sort,
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sticker_pack_items');
        DB::table('market_items')
            ->whereIn('type', [MarketItem::TYPE_PROFILE_THEME, MarketItem::TYPE_STICKER_PACK])
            ->delete();
    }
};
