<?php

namespace App\Filament\Widgets;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use App\Features\UserStatus\Models\UserStatus;
use App\Features\Wallet\Models\RechargeOrder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', (string) User::query()->count())
                ->description('Registered accounts')
                ->icon('heroicon-o-users'),
            Stat::make(
                'Online Users',
                (string) UserStatus::query()->where('is_online', true)->count(),
            )
                ->description('Currently online')
                ->icon('heroicon-o-signal'),
            Stat::make(
                'Active Live Rooms',
                (string) LiveRoom::query()->where('status', 'live')->count(),
            )
                ->description('Streams live now')
                ->icon('heroicon-o-video-camera'),
            Stat::make(
                'Revenue',
                number_format((int) RechargeOrder::query()
                    ->where('status', RechargeOrder::STATUS_COMPLETED)
                    ->sum('price')),
            )
                ->description('Completed recharges')
                ->icon('heroicon-o-banknotes'),
            Stat::make(
                'Gift Volume',
                number_format((int) GiftTransaction::query()->sum('coins')),
            )
                ->description('Coins gifted')
                ->icon('heroicon-o-gift'),
        ];
    }
}
