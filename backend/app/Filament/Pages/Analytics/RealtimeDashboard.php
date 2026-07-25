<?php

namespace App\Filament\Pages\Analytics;

use App\Filament\Pages\Analytics\Concerns\AuthorizesAnalyticsAccess;
use App\Filament\Widgets\Analytics\CcuOverviewWidget;
use App\Filament\Widgets\Analytics\TopGamesWidget;
use Filament\Pages\Page;

class RealtimeDashboard extends Page
{
    use AuthorizesAnalyticsAccess;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Realtime';

    protected static ?string $title = 'Realtime Dashboard';

    protected static ?string $slug = 'realtime-dashboard';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.analytics.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            CcuOverviewWidget::class,
            TopGamesWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
