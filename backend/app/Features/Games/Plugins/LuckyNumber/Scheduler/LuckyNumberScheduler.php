<?php

namespace App\Features\Games\Plugins\LuckyNumber\Scheduler;

use App\Features\Games\Plugins\LuckyNumber\Services\LuckyNumberService;

final readonly class LuckyNumberScheduler
{
    public function __construct(private LuckyNumberService $service) {}

    public function processDailyJackpots(): int
    {
        return $this->service->processDailyJackpots();
    }

    public function processDueDraws(int $limit = 50): int
    {
        return $this->service->processDueDraws($limit);
    }
}
