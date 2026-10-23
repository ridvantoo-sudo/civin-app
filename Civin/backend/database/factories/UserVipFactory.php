<?php

namespace Database\Factories;

use App\Features\Users\Models\User;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Models\VipLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserVip>
 */
class UserVipFactory extends Factory
{
    protected $model = UserVip::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vip_level_id' => VipLevel::factory(),
            'status' => UserVip::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(29),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserVip::STATUS_EXPIRED,
            'started_at' => now()->subDays(40),
            'expires_at' => now()->subMinute(),
        ]);
    }
}
