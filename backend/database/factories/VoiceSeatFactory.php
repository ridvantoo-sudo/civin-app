<?php

namespace Database\Factories;

use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Models\VoiceSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoiceSeatFactory extends Factory
{
    protected $model = VoiceSeat::class;

    public function definition(): array
    {
        return [
            'room_id' => VoiceRoom::factory(),
            'seat_index' => 0,
            'user_id' => null,
            'status' => VoiceSeat::STATUS_EMPTY,
            'is_muted' => false,
            'stream_uid' => null,
            'updated_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => VoiceSeat::STATUS_PENDING,
        ]);
    }

    public function occupied(): static
    {
        return $this->state(fn (): array => [
            'status' => VoiceSeat::STATUS_OCCUPIED,
        ]);
    }
}
