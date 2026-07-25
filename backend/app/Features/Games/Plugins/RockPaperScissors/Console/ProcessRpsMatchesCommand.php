<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Console;

use App\Features\Games\Plugins\RockPaperScissors\Scheduler\RpsScheduler;
use Illuminate\Console\Command;

final class ProcessRpsMatchesCommand extends Command
{
    protected $signature = 'games:rps:process {--limit=100 : Max due rounds/matches/challenges per bucket}';

    protected $description = 'Resolve timed-out Rock Paper Scissors rounds, settle stale matches, and expire challenges';

    public function handle(RpsScheduler $scheduler): int
    {
        $result = $scheduler->processDue((int) $this->option('limit'));

        $this->info(sprintf(
            'RPS processed: rounds_resolved=%d matches_settled=%d challenges_expired=%d',
            $result['rounds_resolved'],
            $result['matches_settled'],
            $result['challenges_expired'],
        ));

        return self::SUCCESS;
    }
}
