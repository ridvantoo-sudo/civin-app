<?php

namespace App\Features\Games\Actions;

use App\Features\Games\DTOs\PlayGameData;
use App\Features\Games\DTOs\PlayGameResult;
use App\Features\Games\Services\GameService;
use App\Features\Users\Models\User;

final readonly class PlayGame
{
    public function __construct(private GameService $games) {}

    public function execute(User $user, PlayGameData $data): PlayGameResult
    {
        return $this->games->play($user, $data);
    }
}
