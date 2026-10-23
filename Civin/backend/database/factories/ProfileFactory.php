<?php

namespace Database\Factories;

use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->name(),
            'bio' => fake()->optional()->sentence(),
            'avatar_url' => fake()->optional()->imageUrl(),
            'cover_image_url' => fake()->optional()->imageUrl(1200, 400),
            'level' => fake()->numberBetween(1, 100),
            'is_vip' => false,
            'is_private' => false,
            'followers_count' => 0,
            'following_count' => 0,
            'likes_count' => 0,
        ];
    }
}
