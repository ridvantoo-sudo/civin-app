<?php

namespace App\Features\Games\Plugins\LuckyNumber\Console;

use App\Features\Games\Plugins\LuckyNumber\Scheduler\LuckyNumberScheduler;
use Illuminate\Console\Command;

final class ProcessLuckyNumberJackpotsCommand extends Command
{
    protected $signature = 'games:lucky-number:process-jackpots';

    protected $description = 'Seed or rotate Lucky Number daily jackpot pools';

    public function handle(LuckyNumberScheduler $scheduler): int
    {
        $processed = $scheduler->processDailyJackpots();
        $this->info("Processed {$processed} daily jackpot pool(s).");

        return self::SUCCESS;
    }
}
