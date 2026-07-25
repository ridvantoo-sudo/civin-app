<?php

namespace App\Filament\Pages\Analytics;

use App\Filament\Pages\Analytics\Concerns\AuthorizesAnalyticsAccess;
use App\Filament\Widgets\Analytics\KpiOverviewWidget;
use App\Filament\Widgets\Analytics\RevenueChartWidget;
use Filament\Pages\Page;

class RevenueDashboard extends Page
{
    use AuthorizesAnalyticsAccess;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Revenue';

    protected static ?string $title = 'Revenue Dashboard';

    protected static ?string $slug = 'revenue-dashboard';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.analytics.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            KpiOverviewWidget::class,
            RevenueChartWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
