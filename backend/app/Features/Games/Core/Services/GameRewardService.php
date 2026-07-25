<?php

namespace App\Features\Games\Core\Services;

use App\Features\Games\Services\GameService;

final readonly class GameRewardService
{
    public function __construct(private GameService $games) {}

    // Rewards settle through WalletRepository inside GameService — no duplicate wallet logic.
}
