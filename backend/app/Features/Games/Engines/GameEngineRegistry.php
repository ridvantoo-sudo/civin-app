<?php

namespace App\Features\Games\Engines;

use Illuminate\Support\Collection;

final class GameEngineRegistry
{
    /** @var array<string, GameEngine> */
    private array $engines = [];

    public function __construct(
        LuckyWheelEngine $luckyWheel,
        LuckyDiceEngine $luckyDice,
        LuckyNumberEngine $luckyNumber,
        TreasureBoxEngine $treasureBox,
        QuizEngine $quiz,
        SlotMachineEngine $slotMachine,
        RockPaperScissorsEngine $rps,
    ) {
        foreach ([
            $luckyWheel,
            $luckyDice,
            $luckyNumber,
            $treasureBox,
            $quiz,
            $slotMachine,
            $rps,
        ] as $engine) {
            $this->engines[$engine->slug()] = $engine;
        }
    }

    public function get(string $slug): ?GameEngine
    {
        return $this->engines[$slug] ?? null;
    }

    /** @return Collection<int, string> */
    public function slugs(): Collection
    {
        return collect(array_keys($this->engines));
    }
}
