<?php

namespace App\Filament\Pages\Analytics;

use App\Filament\Pages\Analytics\Concerns\AuthorizesAnalyticsAccess;
use App\Filament\Widgets\Analytics\FraudAlertsWidget;
use Filament\Pages\Page;

class FraudDashboard extends Page
{
    use AuthorizesAnalyticsAccess;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Fraud';

    protected static ?string $title = 'Fraud Dashboard';

    protected static ?string $slug = 'fraud-dashboard';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.analytics.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            FraudAlertsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
