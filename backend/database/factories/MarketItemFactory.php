<?php

namespace Database\Factories;

use App\Features\Marketplace\Models\MarketItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MarketItem> */
class MarketItemFactory extends Factory
{
    protected $model = MarketItem::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(MarketItem::types()),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'icon_url' => fake()->imageUrl(),
            'asset_url' => fake()->url(),
            'coin_price' => fake()->numberBetween(100, 5000),
            'duration_days' => fake()->optional()->numberBetween(7, 90),
            'is_limited' => false,
            'stock_total' => null,
            'stock_remaining' => null,
            'special_value' => null,
            'membership_required' => null,
            'status' => MarketItem::STATUS_ACTIVE,
            'sort_order' => fake()->numberBetween(0, 20),
            'starts_at' => null,
            'ends_at' => null,
            'metadata' => null,
        ];
    }
}
