<?php

namespace App\Filament\Widgets;

use App\Features\Gifts\Models\GiftTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GiftVolumeWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $today = (int) GiftTransaction::query()
            ->whereDate('created_at', today())
            ->sum('coins');

        $total = (int) GiftTransaction::query()->sum('coins');

        return [
            Stat::make('Gift Volume Today', number_format($today))
                ->description('Lifetime: '.number_format($total).' coins')
                ->icon('heroicon-o-gift'),
        ];
    }
}
