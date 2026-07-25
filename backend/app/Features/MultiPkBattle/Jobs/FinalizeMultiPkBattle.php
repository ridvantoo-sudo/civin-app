<?php

namespace App\Features\MultiPkBattle\Jobs;

use App\Features\MultiPkBattle\Services\MultiPkBattleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class FinalizeMultiPkBattle implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $battleId) {}

    public function handle(MultiPkBattleService $battles): void
    {
        $battles->finalizeIfDue($this->battleId);
    }
}
