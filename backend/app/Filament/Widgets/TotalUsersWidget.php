<?php

namespace App\Filament\Widgets;

use App\Features\Users\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalUsersWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', (string) User::query()->count())
                ->icon('heroicon-o-users'),
        ];
    }
}
