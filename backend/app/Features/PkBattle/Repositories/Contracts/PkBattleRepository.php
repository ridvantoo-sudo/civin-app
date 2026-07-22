<?php

namespace App\Features\PkBattle\Repositories\Contracts;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\PkBattle\DTOs\RequestPkBattleData;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Models\PkScore;
use App\Features\Users\Models\User;

interface PkBattleRepository
{
    public function find(string $battleId): ?PkBattle;

    public function show(PkBattle $battle): PkBattle;

    public function findActiveForRoom(string $roomId): ?PkBattle;

    public function findRunningForRoom(string $roomId): ?PkBattle;

    public function request(LiveRoom $roomA, LiveRoom $roomB, User $hostA, RequestPkBattleData $data): PkBattle;

    public function accept(PkBattle $battle, LiveRoom $roomB, User $hostB): PkBattle;

    public function start(PkBattle $battle, User $actor): PkBattle;

    public function end(PkBattle $battle, User $actor): PkBattle;

    public function applyGiftScore(GiftTransaction $transaction): ?PkScore;
}
