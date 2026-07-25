<?php

namespace App\Features\Games\Actions;

use App\Features\Games\Models\Game;
use App\Features\Games\Services\GameService;
use Illuminate\Support\Collection;

final readonly class ListGames
{
    public function __construct(private GameService $games) {}

    /** @return Collection<int, Game> */
    public function execute(): Collection
    {
        return $this->games->catalog();
    }
}
