<?php

namespace Database\Factories;

use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Models\VoiceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoiceSessionFactory extends Factory
{
    protected $model = VoiceSession::class;

    public function definition(): array
    {
        return [
            'room_id' => VoiceRoom::factory(),
            'duration' => 0,
            'peak_participants' => 0,
        ];
    }
}
