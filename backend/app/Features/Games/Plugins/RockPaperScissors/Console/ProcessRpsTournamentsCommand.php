<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Console;

use App\Features\Games\Plugins\RockPaperScissors\Scheduler\RpsScheduler;
use Illuminate\Console\Command;

final class ProcessRpsTournamentsCommand extends Command
{
    protected $signature = 'games:rps:process-tournaments
        {--limit=100 : Max tournaments per transition bucket}
        {--daily : Also ensure today\'s daily cup exists}';

    protected $description = 'Start scheduled RPS tournaments, build brackets, spawn matches, advance and finish them';

    public function handle(RpsScheduler $scheduler): int
    {
        if ($this->option('daily')) {
            $created = $scheduler->ensureDailyTournament();
            $this->info($created === 1 ? 'Daily RPS cup created.' : 'Daily RPS cup already exists.');
        }

        $result = $scheduler->processTournaments((int) $this->option('limit'));

        $this->info(sprintf(
            'RPS tournaments: started=%d brackets_generated=%d matches_spawned=%d brackets_advanced=%d finished=%d',
            $result['started'],
            $result['brackets_generated'],
            $result['matches_spawned'],
            $result['brackets_advanced'],
            $result['finished'],
        ));

        return self::SUCCESS;
    }
}
