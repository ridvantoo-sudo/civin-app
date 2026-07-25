<?php

namespace App\Features\Games\Plugins\TreasureBox\Console;

use App\Features\Games\Plugins\TreasureBox\Scheduler\TreasureBoxScheduler;
use Illuminate\Console\Command;

final class ProcessTreasureBoxesCommand extends Command
{
    protected $signature = 'games:treasure-box:process {--limit=100 : Max boxes per due bucket}';

    protected $description = 'Start scheduled treasure boxes, draw winners, and expire unfinished boxes';

    public function handle(TreasureBoxScheduler $scheduler): int
    {
        $result = $scheduler->processDue((int) $this->option('limit'));

        $this->info(sprintf(
            'Treasure boxes processed: started=%d drawn=%d expired=%d',
            $result['started'],
            $result['drawn'],
            $result['expired'],
        ));

        return self::SUCCESS;
    }
}
