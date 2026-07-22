<?php

namespace Database\Factories;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveViewer;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveViewerFactory extends Factory
{
    protected $model = LiveViewer::class;

    public function definition(): array
    {
        return [
            'room_id' => LiveRoom::factory(),
            'user_id' => User::factory(),
            'joined_at' => now(),
            'left_at' => null,
        ];
    }
}
