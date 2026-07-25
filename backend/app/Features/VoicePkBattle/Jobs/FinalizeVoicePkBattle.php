<?php

namespace App\Features\VoicePkBattle\Jobs;

use App\Features\VoicePkBattle\Services\VoicePkBattleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class FinalizeVoicePkBattle implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $battleId) {}

    public function handle(VoicePkBattleService $battles): void
    {
        $battles->finalizeIfDue($this->battleId);
    }
}
