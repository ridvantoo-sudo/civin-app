<?php

namespace Database\Factories;

use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'is_guest' => false,
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            Wallet::query()->firstOrCreate(
                ['user_id' => $user->getKey()],
                ['coins_balance' => 0, 'diamonds_balance' => 0],
            );
        });
    }

    public function withCoins(int $coins = 1000): static
    {
        return $this->afterCreating(function (User $user) use ($coins): void {
            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => $user->getKey()],
                ['coins_balance' => 0, 'diamonds_balance' => 0],
            );
            $wallet->forceFill(['coins_balance' => $coins])->save();
        });
    }

    public function withDiamonds(int $diamonds = 1000): static
    {
        return $this->afterCreating(function (User $user) use ($diamonds): void {
            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => $user->getKey()],
                ['coins_balance' => 0, 'diamonds_balance' => 0],
            );
            $wallet->forceFill(['diamonds_balance' => $diamonds])->save();
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
