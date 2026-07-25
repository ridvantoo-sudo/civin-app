<?php

namespace App\Features\Games\Plugins\TreasureBox\Scheduler;

use App\Features\Games\Plugins\TreasureBox\Services\TreasureBoxService;

final readonly class TreasureBoxScheduler
{
    public function __construct(private TreasureBoxService $service) {}

    /** @return array{started: int, drawn: int, expired: int, auto_created: int} */
    public function processDue(int $limit = 100): array
    {
        return $this->service->processDue($limit);
    }
}
