<?php

namespace App\Features\PkBattle\Actions;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Services\PkBattleService;
use App\Features\Users\Models\User;

final readonly class AcceptPkBattle
{
    public function __construct(private PkBattleService $battles) {}

    public function execute(LiveRoom $room, User $host): PkBattle
    {
        return $this->battles->accept($room, $host);
    }
}
