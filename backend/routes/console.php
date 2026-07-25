<?php

use App\Features\Games\Plugins\LuckyNumber\Scheduler\LuckyNumberScheduler;
use App\Features\Games\Plugins\Quiz\Scheduler\QuizScheduler;
use App\Features\Games\Plugins\RockPaperScissors\Scheduler\RpsScheduler;
use App\Features\Games\Plugins\SlotMachine\Scheduler\SlotMachineScheduler;
use App\Features\Games\Plugins\TreasureBox\Scheduler\TreasureBoxScheduler;
use App\Features\Marketplace\Jobs\ExpireMarketplaceItems;
use App\Features\Ranking\Jobs\CalculateDailyRankings;
use App\Features\Ranking\Jobs\CalculateWeeklyRankings;
use App\Features\Svip\Jobs\ExpireSvipSubscriptions;
use App\Features\Vip\Jobs\ExpireVipSubscriptions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CalculateDailyRankings)
    ->dailyAt('00:05')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::job(new CalculateWeeklyRankings)
    ->weeklyOn(1, '00:15')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::job(new ExpireVipSubscriptions)
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::job(new ExpireSvipSubscriptions)
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::job(new ExpireMarketplaceItems)
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(TreasureBoxScheduler::class)->processDue())
    ->everyMinute()
    ->name('games:treasure-box:process')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(SlotMachineScheduler::class)->processDailyJackpots())
    ->dailyAt('00:10')
    ->name('games:slot-machine:daily-jackpots')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(LuckyNumberScheduler::class)->processDailyJackpots())
    ->dailyAt('00:12')
    ->name('games:lucky-number:daily-jackpots')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(LuckyNumberScheduler::class)->processDueDraws())
    ->everyMinute()
    ->name('games:lucky-number:process-draws')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(QuizScheduler::class)->processDue())
    ->everyMinute()
    ->name('games:quiz:process')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(QuizScheduler::class)->processTournaments())
    ->everyMinute()
    ->name('games:quiz:process-tournaments')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(RpsScheduler::class)->processDue())
    ->everyMinute()
    ->name('games:rps:process')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(RpsScheduler::class)->processTournaments())
    ->everyMinute()
    ->name('games:rps:process-tournaments')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(RpsScheduler::class)->ensureDailyTournament())
    ->dailyAt('00:14')
    ->name('games:rps:daily-tournament')
    ->onOneServer()
    ->withoutOverlapping();

Artisan::command('games:slot-machine:process-jackpots', function () {
    $processed = app(SlotMachineScheduler::class)->processDailyJackpots();
    $this->info("Processed {$processed} daily jackpot pool(s).");
})->purpose('Seed or rotate Slot Machine daily jackpot pools');

Artisan::command('games:treasure-box:process {--limit=100}', function () {
    $result = app(TreasureBoxScheduler::class)->processDue((int) $this->option('limit'));

    $this->info(sprintf(
        'Treasure boxes processed: started=%d drawn=%d expired=%d auto_created=%d',
        $result['started'],
        $result['drawn'],
        $result['expired'],
        $result['auto_created'] ?? 0,
    ));
})->purpose('Start scheduled treasure boxes, create auto rules, draw winners, and expire unfinished boxes');

Artisan::command('games:quiz:process {--limit=100}', function () {
    $result = app(QuizScheduler::class)->processDue((int) $this->option('limit'));

    $this->info(sprintf(
        'Quizzes processed: advanced=%d settled=%d tournaments_started=%d tournaments_finished=%d',
        $result['advanced'],
        $result['settled'],
        $result['tournaments_started'],
        $result['tournaments_finished'],
    ));
})->purpose('Advance timed-out quiz questions, settle sessions, and process tournaments');
