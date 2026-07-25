<?php

namespace App\Features\Games\Plugins\Quiz\Console;

use App\Features\Games\Plugins\Quiz\Scheduler\QuizScheduler;
use Illuminate\Console\Command;

final class ProcessQuizzesCommand extends Command
{
    protected $signature = 'games:quiz:process {--limit=100 : Max due sessions/tournaments per bucket}';

    protected $description = 'Advance timed-out quiz questions, settle finished sessions, and process tournaments';

    public function handle(QuizScheduler $scheduler): int
    {
        $result = $scheduler->processDue((int) $this->option('limit'));

        $this->info(sprintf(
            'Quizzes processed: advanced=%d settled=%d tournaments_started=%d tournaments_finished=%d',
            $result['advanced'],
            $result['settled'],
            $result['tournaments_started'],
            $result['tournaments_finished'],
        ));

        return self::SUCCESS;
    }
}
