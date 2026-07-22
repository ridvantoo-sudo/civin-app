<?php

namespace App\Features\PkBattle\Actions;

use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Services\PkBattleService;

final readonly class ShowPkBattle
{
    public function __construct(private PkBattleService $battles) {}

    public function execute(PkBattle $battle): PkBattle
    {
        return $this->battles->show($battle);
    }
}
