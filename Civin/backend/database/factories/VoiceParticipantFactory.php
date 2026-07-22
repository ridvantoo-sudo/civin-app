<?php

namespace Database\Factories;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceParticipant;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoiceParticipantFactory extends Factory
{
    protected $model = VoiceParticipant::class;

    public function definition(): array
    {
        return [
            'room_id' => VoiceRoom::factory(),
            'user_id' => User::factory(),
            'role' => VoiceParticipant::ROLE_AUDIENCE,
            'joined_at' => now(),
            'left_at' => null,
        ];
    }

    public function host(): static
    {
        return $this->state(fn (): array => [
            'role' => VoiceParticipant::ROLE_HOST,
        ]);
    }

    public function speaker(): static
    {
        return $this->state(fn (): array => [
            'role' => VoiceParticipant::ROLE_SPEAKER,
        ]);
    }
}
