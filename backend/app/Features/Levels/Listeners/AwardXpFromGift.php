<?php

namespace App\Features\Levels\Listeners;

use App\Features\Gifts\Events\GiftSent;
use App\Features\Levels\Services\LevelService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class AwardXpFromGift implements ShouldQueueAfterCommit
{
    public function __construct(private readonly LevelService $levels) {}

    public function handle(GiftSent $event): void
    {
        $transaction = $event->transaction->loadMissing(['sender', 'receiver']);
        $coins = (int) $transaction->coins;

        if ($transaction->sender instanceof User) {
            $this->levels->awardGiftSend($transaction->sender, (string) $transaction->getKey(), $coins);
        }

        if ($transaction->receiver instanceof User) {
            $this->levels->awardGiftReceive($transaction->receiver, (string) $transaction->getKey(), $coins);
        }
    }
}
