<?php

namespace App\Features\Games\Actions;

use App\Features\Games\Services\GameService;
use App\Features\Users\Models\User;

final readonly class GetGameStats
{
    public function __construct(private GameService $games) {}

    /** @return array<string, mixed> */
    public function execute(User $user, ?string $gameSlug = null): array
    {
        return $this->games->stats($user, $gameSlug);
    }
}
