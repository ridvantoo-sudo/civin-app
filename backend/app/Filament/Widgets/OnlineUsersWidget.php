<?php

namespace App\Filament\Widgets;

use App\Features\UserStatus\Models\UserStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OnlineUsersWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make(
                'Online Users',
                (string) UserStatus::query()->where('is_online', true)->count(),
            )->icon('heroicon-o-signal'),
        ];
    }
}
