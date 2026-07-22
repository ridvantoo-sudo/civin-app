<?php

namespace Database\Factories;

use App\Features\LiveStreaming\Models\LiveCategory;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LiveRoomFactory extends Factory
{
    protected $model = LiveRoom::class;

    public function definition(): array
    {
        return [
            'host_id' => User::factory(),
            'category_id' => LiveCategory::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'thumbnail' => fake()->optional()->imageUrl(1280, 720),
            'agora_channel_name' => 'live_'.Str::lower(Str::random(24)),
            'stream_uid' => fake()->unique()->numberBetween(1, 4294967295),
            'status' => 'created',
            'viewer_count' => 0,
        ];
    }
}
