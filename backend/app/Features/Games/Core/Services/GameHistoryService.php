<?php

namespace App\Features\Games\Core\Services;

use App\Features\Games\Services\GameService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class GameHistoryService
{
    public function __construct(private GameService $games) {}

    public function history(User $user, ?string $gameSlug = null, int $perPage = 30): LengthAwarePaginator
    {
        return $this->games->history($user, $gameSlug, $perPage);
    }
}
