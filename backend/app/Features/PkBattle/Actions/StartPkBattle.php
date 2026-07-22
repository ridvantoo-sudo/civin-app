<?php

namespace App\Features\PkBattle\Actions;

use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Services\PkBattleService;
use App\Features\Users\Models\User;

final readonly class StartPkBattle
{
    public function __construct(private PkBattleService $battles) {}

    public function execute(PkBattle $battle, User $actor): PkBattle
    {
        return $this->battles->start($battle, $actor);
    }
}
