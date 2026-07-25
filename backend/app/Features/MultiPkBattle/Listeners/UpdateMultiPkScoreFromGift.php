<?php

namespace App\Features\MultiPkBattle\Listeners;

use App\Features\Gifts\Events\GiftSent;
use App\Features\MultiPkBattle\Services\MultiPkBattleService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class UpdateMultiPkScoreFromGift implements ShouldQueueAfterCommit
{
    public function __construct(private readonly MultiPkBattleService $battles) {}

    public function handle(GiftSent $event): void
    {
        $this->battles->applyGift($event->transaction);
    }
}
