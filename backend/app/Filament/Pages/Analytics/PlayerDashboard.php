<?php

namespace App\Filament\Pages\Analytics;

use App\Filament\Pages\Analytics\Concerns\AuthorizesAnalyticsAccess;
use App\Filament\Widgets\Analytics\KpiOverviewWidget;
use App\Filament\Widgets\Analytics\TopGamesWidget;
use Filament\Pages\Page;

class PlayerDashboard extends Page
{
    use AuthorizesAnalyticsAccess;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Players';

    protected static ?string $title = 'Player Dashboard';

    protected static ?string $slug = 'player-dashboard';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.analytics.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            KpiOverviewWidget::class,
            TopGamesWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
