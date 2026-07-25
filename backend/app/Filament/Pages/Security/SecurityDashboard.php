<?php

namespace App\Filament\Pages\Security;

use App\Filament\Pages\Security\Concerns\SecurityPage;
use App\Filament\Widgets\Security\RiskDistributionWidget;
use App\Filament\Widgets\Security\SecurityOverviewWidget;
use App\Filament\Widgets\Security\SignalTrendWidget;
use App\Filament\Widgets\Security\ThreatFeedWidget;
use Filament\Pages\Page;

class SecurityDashboard extends Page
{
    use SecurityPage;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Security Dashboard';

    protected static ?string $title = 'Security Dashboard';

    protected static ?string $slug = 'security';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.security.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            SecurityOverviewWidget::class,
            SignalTrendWidget::class,
            RiskDistributionWidget::class,
            ThreatFeedWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
