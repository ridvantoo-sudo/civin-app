<?php

namespace App\Features\PkBattle\Listeners;

use App\Features\Gifts\Events\GiftSent;
use App\Features\PkBattle\Services\PkBattleService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class UpdatePkScoreFromGift implements ShouldQueueAfterCommit
{
    public function __construct(private readonly PkBattleService $battles) {}

    public function handle(GiftSent $event): void
    {
        $this->battles->applyGiftScore($event->transaction);
    }
}
