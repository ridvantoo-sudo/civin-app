<?php

namespace App\Features\Games\Plugins\SlotMachine\Console;

use App\Features\Games\Plugins\SlotMachine\Scheduler\SlotMachineScheduler;
use Illuminate\Console\Command;

final class ProcessSlotMachineJackpotsCommand extends Command
{
    protected $signature = 'slot-machine:process-jackpots';

    protected $description = 'Seed or rotate Slot Machine daily jackpot pools';

    public function handle(SlotMachineScheduler $scheduler): int
    {
        $processed = $scheduler->processDailyJackpots();
        $this->info("Processed {$processed} daily jackpot pool(s).");

        return self::SUCCESS;
    }
}
