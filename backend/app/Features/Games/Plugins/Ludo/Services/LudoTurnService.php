<?php

namespace App\Features\Games\Plugins\Ludo\Services;

use App\Features\Games\Plugins\Ludo\Models\LudoTurn;

final class LudoTurnService
{
    public function active(string $matchId): ?LudoTurn
    {
        return LudoTurn::query()->where('match_id', $matchId)->where('status', 'active')->latest('turn_index')->first();
    }
}
