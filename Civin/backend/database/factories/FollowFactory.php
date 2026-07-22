<?php

namespace Database\Factories;

use App\Features\Followers\Models\Follow;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FollowFactory extends Factory
{
    protected $model = Follow::class;

    public function definition(): array
    {
        return [
            'follower_id' => User::factory(),
            'followed_id' => User::factory(),
            'status' => 'accepted',
            'accepted_at' => now(),
        ];
    }
}
