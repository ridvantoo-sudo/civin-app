<?php

namespace App\Features\Games\Actions;

use App\Features\Games\DTOs\CreateMatchData;
use App\Features\Games\Models\GameMatch;
use App\Features\Games\Services\GameService;
use App\Features\Users\Models\User;

final readonly class CreateGameMatch
{
    public function __construct(private GameService $games) {}

    public function execute(User $user, CreateMatchData $data): GameMatch
    {
        return $this->games->createMatch($user, $data);
    }
}
