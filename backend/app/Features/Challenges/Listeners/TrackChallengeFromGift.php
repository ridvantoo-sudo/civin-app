<?php

namespace App\Features\Challenges\Listeners;

use App\Features\Challenges\Services\ChallengeService;
use App\Features\Gifts\Events\GiftSent;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class TrackChallengeFromGift implements ShouldQueueAfterCommit
{
    public function __construct(private readonly ChallengeService $challenges) {}

    public function handle(GiftSent $event): void
    {
        $transaction = $event->transaction->loadMissing(['sender', 'receiver']);
        $coins = (int) $transaction->coins;

        if ($transaction->sender instanceof User) {
            $this->challenges->trackGiftSend($transaction->sender, $coins);
        }

        if ($transaction->receiver instanceof User) {
            $this->challenges->trackGiftReceive($transaction->receiver, $coins);
        }
    }
}
