<?php

namespace App\Features\Ranking\Jobs;

use App\Features\Ranking\Models\Ranking;
use App\Features\Ranking\Services\RankingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CalculateDailyRankings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(RankingService $rankings): void
    {
        $rankings->recalculatePeriod(Ranking::PERIOD_DAILY);
    }
}
