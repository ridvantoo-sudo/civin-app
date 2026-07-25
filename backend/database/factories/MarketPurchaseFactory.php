<?php

namespace Database\Factories;

use App\Features\Marketplace\Models\MarketItem;
use App\Features\Marketplace\Models\MarketPurchase;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MarketPurchase> */
class MarketPurchaseFactory extends Factory
{
    protected $model = MarketPurchase::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'market_item_id' => MarketItem::factory(),
            'coins' => fake()->numberBetween(100, 5000),
            'metadata' => null,
            'created_at' => now(),
        ];
    }
}
