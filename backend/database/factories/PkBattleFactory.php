<?php

namespace Database\Factories;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PkBattleFactory extends Factory
{
    protected $model = PkBattle::class;

    public function definition(): array
    {
        $hostA = User::factory();
        $hostB = User::factory();

        return [
            'room_a_id' => LiveRoom::factory()->state(['host_id' => $hostA]),
            'room_b_id' => LiveRoom::factory()->state(['host_id' => $hostB]),
            'host_a_id' => $hostA,
            'host_b_id' => $hostB,
            'status' => PkBattle::STATUS_WAITING,
            'duration_seconds' => PkBattle::DEFAULT_DURATION_SECONDS,
            'started_at' => null,
            'ended_at' => null,
            'winner_id' => null,
            'created_at' => now(),
        ];
    }

    public function waiting(): static
    {
        return $this->state(fn (): array => [
            'status' => PkBattle::STATUS_WAITING,
            'started_at' => null,
            'ended_at' => null,
            'winner_id' => null,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => PkBattle::STATUS_RUNNING,
            'started_at' => now(),
            'ended_at' => null,
            'winner_id' => null,
        ]);
    }

    public function finished(?User $winner = null): static
    {
        return $this->state(fn (): array => [
            'status' => PkBattle::STATUS_FINISHED,
            'started_at' => now()->subMinutes(3),
            'ended_at' => now(),
            'winner_id' => $winner?->id,
        ]);
    }
}
