<?php

namespace App\Filament\Pages\Analytics;

use App\Filament\Pages\Analytics\Concerns\AuthorizesAnalyticsAccess;
use App\Filament\Widgets\Analytics\CcuOverviewWidget;
use App\Filament\Widgets\Analytics\KpiOverviewWidget;
use App\Filament\Widgets\Analytics\RevenueChartWidget;
use App\Filament\Widgets\Analytics\TopGamesWidget;
use Filament\Pages\Page;

class ExecutiveDashboard extends Page
{
    use AuthorizesAnalyticsAccess;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Analytics Dashboard';

    protected static ?string $title = 'Analytics Dashboard';

    protected static ?string $slug = 'analytics-dashboard';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.analytics.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            KpiOverviewWidget::class,
            RevenueChartWidget::class,
            CcuOverviewWidget::class,
            TopGamesWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 2;
    }
}
