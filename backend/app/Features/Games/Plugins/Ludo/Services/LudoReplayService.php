<?php

namespace App\Features\Games\Plugins\Ludo\Services;

use App\Features\Games\Plugins\Ludo\Models\LudoReplay;
use App\Features\Users\Models\User;

final class LudoReplayService
{
    public function list(User $user, int $perPage = 30)
    {
        return LudoReplay::query()->whereHas('match.players', fn ($q) => $q->where('user_id', $user->getKey()))
            ->latest()->paginate($perPage);
    }
}
