<?php

use App\Features\Ranking\Jobs\CalculateDailyRankings;
use App\Features\Ranking\Jobs\CalculateWeeklyRankings;
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
