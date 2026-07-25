<?php

namespace App\Filament\Widgets;

use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Models\TreasureBoxStatistic;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TreasureBoxDashboardWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $createdToday = TreasureBox::query()->whereDate('created_at', today())->count();
        $openNow = TreasureBox::query()
            ->whereIn('status', [
                TreasureBox::STATUS_SCHEDULED,
                TreasureBox::STATUS_OPEN,
                TreasureBox::STATUS_COUNTDOWN,
            ])
            ->count();
        $finishedToday = TreasureBox::query()
            ->where('status', TreasureBox::STATUS_FINISHED)
            ->whereDate('updated_at', today())
            ->count();
        $jackpots = (int) TreasureBoxStatistic::query()->sum('jackpots_won');

        return [
            Stat::make('Treasures Today', number_format($createdToday))
                ->description('Created in the last 24h window')
                ->icon('heroicon-o-cube'),
            Stat::make('Active / Scheduled', number_format($openNow))
                ->description('Open, countdown, or scheduled')
                ->icon('heroicon-o-clock'),
            Stat::make('Finished Today', number_format($finishedToday))
                ->description('Completed draws today')
                ->icon('heroicon-o-check-badge'),
            Stat::make('Jackpots Won', number_format($jackpots))
                ->description('All-time jackpot wins')
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
