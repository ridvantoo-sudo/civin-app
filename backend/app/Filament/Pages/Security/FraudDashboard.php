<?php

namespace App\Filament\Pages\Security;

use App\Filament\Pages\Security\Concerns\SecurityPage;
use App\Filament\Widgets\Security\FraudSignalsWidget;
use App\Filament\Widgets\Security\TopRiskyAccountsWidget;
use Filament\Pages\Page;

class FraudDashboard extends Page
{
    use SecurityPage;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Fraud Dashboard';

    protected static ?string $title = 'Fraud Dashboard';

    protected static ?string $slug = 'security/fraud';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.security.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            FraudSignalsWidget::class,
            TopRiskyAccountsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
