<?php

namespace Database\Factories;

use App\Features\Users\Models\User;
use App\Features\UserStatus\Models\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserStatusFactory extends Factory
{
    protected $model = UserStatus::class;

    public function definition(): array
    {
        $online = fake()->boolean();

        return [
            'user_id' => User::factory(),
            'is_online' => $online,
            'is_live' => false,
            'last_seen_at' => $online ? null : now(),
        ];
    }
}
