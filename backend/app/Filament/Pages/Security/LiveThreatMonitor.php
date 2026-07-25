<?php

namespace App\Filament\Pages\Security;

use App\Filament\Pages\Security\Concerns\SecurityPage;
use App\Filament\Widgets\Security\SecurityOverviewWidget;
use App\Filament\Widgets\Security\ThreatFeedWidget;
use Filament\Pages\Page;

class LiveThreatMonitor extends Page
{
    use SecurityPage;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Live Threat Monitor';

    protected static ?string $title = 'Live Threat Monitor';

    protected static ?string $slug = 'security/threats';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.security.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            SecurityOverviewWidget::class,
            ThreatFeedWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
