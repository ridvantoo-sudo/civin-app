<?php

namespace Database\Factories;

use App\Features\Vip\Models\VipLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VipLevel>
 */
class VipLevelFactory extends Factory
{
    protected $model = VipLevel::class;

    public function definition(): array
    {
        $level = fake()->unique()->numberBetween(1, 50);

        return [
            'name' => 'VIP '.$level,
            'level' => $level,
            'coin_price' => $level * 500,
            'duration_days' => 30,
            'badge' => 'https://cdn.example.com/vip/badge-'.$level.'.png',
            'profile_frame' => 'https://cdn.example.com/vip/frame-'.$level.'.png',
            'chat_effect' => 'sparkle-'.$level,
            'entrance_animation' => 'https://cdn.example.com/vip/entrance-'.$level.'.json',
            'exclusive_gifts' => $level >= 2,
            'status' => VipLevel::STATUS_ACTIVE,
            'sort_order' => $level,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VipLevel::STATUS_INACTIVE,
        ]);
    }
}
