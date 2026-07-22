<?php

namespace App\Filament\Widgets;

use App\Features\Wallet\Models\RechargeOrder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $today = (int) RechargeOrder::query()
            ->where('status', RechargeOrder::STATUS_COMPLETED)
            ->whereDate('created_at', today())
            ->sum('price');

        $total = (int) RechargeOrder::query()
            ->where('status', RechargeOrder::STATUS_COMPLETED)
            ->sum('price');

        return [
            Stat::make('Revenue Today', number_format($today))
                ->description('Total: '.number_format($total))
                ->icon('heroicon-o-currency-dollar'),
        ];
    }
}
