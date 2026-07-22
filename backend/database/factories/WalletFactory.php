<?php

namespace Database\Factories;

use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'coins_balance' => 0,
            'diamonds_balance' => 0,
        ];
    }

    public function withBalances(int $coins = 0, int $diamonds = 0): static
    {
        return $this->state(fn (): array => [
            'coins_balance' => $coins,
            'diamonds_balance' => $diamonds,
        ]);
    }
}
