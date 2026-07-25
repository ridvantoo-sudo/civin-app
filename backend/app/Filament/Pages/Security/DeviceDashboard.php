<?php

namespace App\Filament\Pages\Security;

use App\Filament\Pages\Security\Concerns\SecurityPage;
use App\Filament\Widgets\Security\DeviceIntegrityWidget;
use Filament\Pages\Page;

class DeviceDashboard extends Page
{
    use SecurityPage;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Device Dashboard';

    protected static ?string $title = 'Device Dashboard';

    protected static ?string $slug = 'security/devices';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.security.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            DeviceIntegrityWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
