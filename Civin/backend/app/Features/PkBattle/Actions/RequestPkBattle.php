<?php

namespace App\Features\PkBattle\Actions;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\PkBattle\DTOs\RequestPkBattleData;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Services\PkBattleService;
use App\Features\Users\Models\User;

final readonly class RequestPkBattle
{
    public function __construct(private PkBattleService $battles) {}

    public function execute(LiveRoom $room, User $host, RequestPkBattleData $data): PkBattle
    {
        return $this->battles->request($room, $host, $data);
    }
}
