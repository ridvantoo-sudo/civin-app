<?php

namespace App\Filament\Pages\Security;

use App\Filament\Pages\Security\Concerns\SecurityPage;
use App\Filament\Widgets\Security\RiskDistributionWidget;
use App\Filament\Widgets\Security\TopRiskyAccountsWidget;
use Filament\Pages\Page;

class RiskDashboard extends Page
{
    use SecurityPage;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Risk Dashboard';

    protected static ?string $title = 'Risk Dashboard';

    protected static ?string $slug = 'security/risk';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.security.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            RiskDistributionWidget::class,
            TopRiskyAccountsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
