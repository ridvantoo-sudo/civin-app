<?php

namespace Database\Factories;

use App\Features\Svip\Models\SvipLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SvipLevel>
 */
class SvipLevelFactory extends Factory
{
    protected $model = SvipLevel::class;

    public function definition(): array
    {
        $level = fake()->unique()->numberBetween(1, 50);

        return [
            'name' => 'SVIP '.$level,
            'level' => $level,
            'coin_price' => $level * 2500,
            'duration_days' => 30,
            'badge' => 'https://cdn.example.com/svip/badge-'.$level.'.png',
            'animated_profile' => 'https://cdn.example.com/svip/profile-'.$level.'.json',
            'profile_frame' => 'https://cdn.example.com/svip/frame-'.$level.'.png',
            'chat_effect' => 'svip-sparkle-'.$level,
            'entrance_animation' => 'https://cdn.example.com/svip/entrance-'.$level.'.json',
            'exclusive_gifts' => true,
            'exclusive_frames' => true,
            'special_privileges' => true,
            'status' => SvipLevel::STATUS_ACTIVE,
            'sort_order' => $level,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SvipLevel::STATUS_INACTIVE,
        ]);
    }
}
