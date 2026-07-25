<?php

namespace App\Features\Games\Actions;

use App\Features\Games\Models\Game;
use App\Features\Games\Services\GameService;

final readonly class GetGame
{
    public function __construct(private GameService $games) {}

    public function execute(string $slug): Game
    {
        return $this->games->show($slug);
    }
}
