<?php

namespace App\Features\Agency\Listeners;

use App\Features\Agency\Services\AgencyService;
use App\Features\Gifts\Events\GiftSent;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class CreateAgencyCommissionFromGift implements ShouldQueueAfterCommit
{
    public function __construct(private readonly AgencyService $agencies) {}

    public function handle(GiftSent $event): void
    {
        $this->agencies->applyGiftCommission($event->transaction);
    }
}
