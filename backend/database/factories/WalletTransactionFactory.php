<?php

namespace Database\Factories;

use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => WalletTransaction::TYPE_ADMIN_ADJUSTMENT,
            'amount' => fake()->numberBetween(1, 500),
            'currency' => WalletTransaction::CURRENCY_COINS,
            'reference_type' => null,
            'reference_id' => null,
            'metadata' => null,
            'created_at' => now(),
        ];
    }
}
