<?php

namespace App\Features\VoicePkBattle\Listeners;

use App\Features\Gifts\Events\GiftSent;
use App\Features\VoicePkBattle\Services\VoicePkBattleService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class UpdateVoicePkScoreFromGift implements ShouldQueueAfterCommit
{
    public function __construct(private readonly VoicePkBattleService $battles) {}

    public function handle(GiftSent $event): void
    {
        $this->battles->applyGift($event->transaction);
    }
}
