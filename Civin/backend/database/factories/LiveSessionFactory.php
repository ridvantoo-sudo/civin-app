<?php

namespace Database\Factories;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveSessionFactory extends Factory
{
    protected $model = LiveSession::class;

    public function definition(): array
    {
        return [
            'room_id' => LiveRoom::factory(),
            'duration' => 0,
            'peak_viewers' => 0,
        ];
    }
}
