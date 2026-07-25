<?php

namespace Database\Factories;

use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoinSeller>
 */
class CoinSellerFactory extends Factory
{
    protected $model = CoinSeller::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->unique()->company().' Coins',
            'contact_info' => fake()->safeEmail(),
            'application_message' => fake()->sentence(),
            'status' => CoinSeller::STATUS_PENDING,
            'commission_rate' => 5,
            'inventory_balance' => 0,
            'total_sold' => 0,
            'total_commission' => 0,
            'sales_count' => 0,
        ];
    }

    public function active(int $inventory = 10000): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CoinSeller::STATUS_ACTIVE,
            'inventory_balance' => $inventory,
            'reviewed_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CoinSeller::STATUS_SUSPENDED,
            'reviewed_at' => now(),
        ]);
    }
}
