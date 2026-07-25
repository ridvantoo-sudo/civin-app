<?php

namespace Database\Factories;

use App\Features\Marketplace\Models\MarketItem;
use App\Features\Marketplace\Models\MarketPurchase;
use App\Features\Marketplace\Models\UserMarketItem;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserMarketItem> */
class UserMarketItemFactory extends Factory
{
    protected $model = UserMarketItem::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'market_item_id' => MarketItem::factory(),
            'purchase_id' => MarketPurchase::factory(),
            'status' => UserMarketItem::STATUS_OWNED,
            'is_equipped' => false,
            'acquired_at' => now(),
            'expires_at' => null,
            'metadata' => null,
        ];
    }
}
