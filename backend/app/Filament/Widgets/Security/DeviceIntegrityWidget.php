<?php

namespace App\Filament\Widgets\Security;

use App\Features\Security\Models\SecurityDevice;
use App\Features\Security\Models\SecurityNetwork;
use App\Filament\Widgets\Security\Concerns\SecurityWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DeviceIntegrityWidget extends StatsOverviewWidget
{
    use SecurityWidget;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $emulators = SecurityDevice::query()->where('is_emulator', true)->count();
        $compromised = SecurityDevice::query()
            ->where(fn ($query) => $query
                ->where('is_rooted', true)
                ->orWhere('is_jailbroken', true)
                ->orWhere('is_tampered', true))
            ->count();
        $cloned = SecurityDevice::query()->where('is_cloned', true)->count();
        $blocked = SecurityDevice::query()->where('is_blocked', true)->count();
        $anonymized = SecurityNetwork::query()
            ->where(fn ($query) => $query
                ->where('is_vpn', true)
                ->orWhere('is_proxy', true)
                ->orWhere('is_tor', true))
            ->count();

        return [
            Stat::make('Emulators', number_format($emulators))
                ->description('Devices reporting emulated hardware')
                ->icon('heroicon-o-computer-desktop')
                ->color($emulators > 0 ? 'warning' : 'success'),
            Stat::make('Integrity compromised', number_format($compromised))
                ->description('Rooted, jailbroken or tampered builds')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color($compromised > 0 ? 'danger' : 'success'),
            Stat::make('Cloned devices', number_format($cloned))
                ->description('One hardware profile, many device ids')
                ->icon('heroicon-o-square-2-stack')
                ->color($cloned > 0 ? 'danger' : 'gray'),
            Stat::make('Blocked devices', number_format($blocked))
                ->description('Denied by an administrator')
                ->icon('heroicon-o-no-symbol')
                ->color($blocked > 0 ? 'danger' : 'gray'),
            Stat::make('Anonymised networks', number_format($anonymized))
                ->description('VPN, proxy or TOR endpoints seen')
                ->icon('heroicon-o-globe-alt')
                ->color($anonymized > 0 ? 'warning' : 'success'),
        ];
    }
}
