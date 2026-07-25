<?php

namespace App\Filament\Widgets;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsReward;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsTournament;
use App\Filament\Concerns\AuthorizesAdminAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RpsDashboardWidget extends StatsOverviewWidget
{
    use AuthorizesAdminAccess;

    protected static ?int $sort = 31;

    public static function canView(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    protected function getStats(): array
    {
        return [
            Stat::make('RPS Matches', RpsMatch::query()->count()),
            Stat::make('Live Matches', RpsMatch::query()->whereIn('status', [
                RpsMatch::STATUS_PENDING,
                RpsMatch::STATUS_MATCHING,
                RpsMatch::STATUS_ACTIVE,
            ])->count()),
            Stat::make('Coins Paid Out', (int) RpsReward::query()
                ->where('status', RpsReward::STATUS_GRANTED)
                ->where('currency', 'coins')
                ->sum('amount')),
            Stat::make('Pending Fulfillment', RpsReward::query()
                ->where('status', RpsReward::STATUS_PENDING)
                ->count()),
            Stat::make('Tournaments', RpsTournament::query()->count()),
        ];
    }
}
