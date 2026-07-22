<?php

namespace Database\Factories;

use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveMessageFactory extends Factory
{
    protected $model = LiveMessage::class;

    public function definition(): array
    {
        return [
            'room_id' => LiveRoom::factory(),
            'user_id' => User::factory(),
            'message' => fake()->sentence(),
            'type' => LiveMessage::TYPE_TEXT,
            'metadata' => null,
        ];
    }
}
