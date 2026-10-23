<?php

namespace Database\Factories;

use App\Features\LiveChat\Models\LiveChatModerator;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveChatModeratorFactory extends Factory
{
    protected $model = LiveChatModerator::class;

    public function definition(): array
    {
        return [
            'room_id' => LiveRoom::factory(),
            'user_id' => User::factory(),
            'role' => LiveChatModerator::ROLE_MODERATOR,
        ];
    }
}
