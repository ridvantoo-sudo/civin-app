<?php

namespace App\Features\Games\Plugins\SlotMachine\Scheduler;

use App\Features\Games\Plugins\SlotMachine\Services\SlotMachineService;

final readonly class SlotMachineScheduler
{
    public function __construct(private SlotMachineService $service) {}

    public function processDailyJackpots(): int
    {
        return $this->service->processDailyJackpots();
    }
}
