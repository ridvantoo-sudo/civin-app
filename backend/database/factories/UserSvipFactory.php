<?php

namespace Database\Factories;

use App\Features\Svip\Models\SvipLevel;
use App\Features\Svip\Models\UserSvip;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSvip>
 */
class UserSvipFactory extends Factory
{
    protected $model = UserSvip::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'svip_level_id' => SvipLevel::factory(),
            'status' => UserSvip::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(29),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserSvip::STATUS_EXPIRED,
            'started_at' => now()->subDays(40),
            'expires_at' => now()->subMinute(),
        ]);
    }
}
