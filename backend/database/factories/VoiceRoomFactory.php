<?php

namespace Database\Factories;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VoiceRoomFactory extends Factory
{
    protected $model = VoiceRoom::class;

    public function definition(): array
    {
        return [
            'host_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'thumbnail' => fake()->optional()->imageUrl(1280, 720),
            'agora_channel_name' => 'voice_'.Str::lower(Str::random(24)),
            'host_uid' => fake()->unique()->numberBetween(1, 4294967295),
            'status' => VoiceRoom::STATUS_LIVE,
            'seat_count' => VoiceRoom::DEFAULT_SEAT_COUNT,
            'participant_count' => 0,
            'started_at' => now(),
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (): array => [
            'status' => VoiceRoom::STATUS_ENDED,
            'ended_at' => now(),
        ]);
    }
}
