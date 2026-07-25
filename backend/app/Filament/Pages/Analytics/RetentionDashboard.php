<?php

namespace App\Filament\Pages\Analytics;

use App\Filament\Pages\Analytics\Concerns\AuthorizesAnalyticsAccess;
use App\Filament\Widgets\Analytics\RetentionOverviewWidget;
use Filament\Pages\Page;

class RetentionDashboard extends Page
{
    use AuthorizesAnalyticsAccess;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Retention';

    protected static ?string $title = 'Retention Dashboard';

    protected static ?string $slug = 'retention-dashboard';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.analytics.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            RetentionOverviewWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
