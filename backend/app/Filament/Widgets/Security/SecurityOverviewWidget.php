<?php

namespace App\Filament\Widgets\Security;

use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Models\SecurityEvent;
use App\Features\Security\Models\SecurityReview;
use App\Features\Security\Models\SecurityScore;
use App\Filament\Widgets\Security\Concerns\SecurityWidget;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SecurityOverviewWidget extends StatsOverviewWidget
{
    use SecurityWidget;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $since = CarbonImmutable::now()->subDay();

        $events = SecurityEvent::query()->where('occurred_at', '>=', $since)->count();
        $openAlerts = SecurityAlert::query()->open()->count();
        $criticalAlerts = SecurityAlert::query()
            ->open()
            ->where('severity', 'critical')
            ->count();
        $pendingReviews = SecurityReview::query()->pending()->count();
        $activeBans = SecurityBan::query()->effective()->count();
        $highRisk = SecurityScore::query()
            ->whereIn('band', [SecurityScore::BAND_HIGH, SecurityScore::BAND_CRITICAL])
            ->count();

        return [
            Stat::make('Events analysed (24h)', number_format($events))
                ->description('Ingested from every module')
                ->icon('heroicon-o-signal')
                ->color('gray'),
            Stat::make('Open alerts', number_format($openAlerts))
                ->description($criticalAlerts.' critical')
                ->icon('heroicon-o-bell-alert')
                ->color($criticalAlerts > 0 ? 'danger' : ($openAlerts > 0 ? 'warning' : 'success')),
            Stat::make('Pending reviews', number_format($pendingReviews))
                ->description('Awaiting a human decision')
                ->icon('heroicon-o-inbox-stack')
                ->color($pendingReviews > 0 ? 'warning' : 'success'),
            Stat::make('Active restrictions', number_format($activeBans))
                ->description('Suspensions and bans in force')
                ->icon('heroicon-o-lock-closed')
                ->color($activeBans > 0 ? 'danger' : 'gray'),
            Stat::make('High-risk accounts', number_format($highRisk))
                ->description('Risk band high or critical')
                ->icon('heroicon-o-shield-exclamation')
                ->color($highRisk > 0 ? 'danger' : 'success'),
            Stat::make('Enforcement', config('security_platform.enforcement_enabled') ? 'Active' : 'Shadow')
                ->description(config('security_platform.enabled') ? 'Engine enabled' : 'Engine disabled')
                ->icon('heroicon-o-cpu-chip')
                ->color(config('security_platform.enforcement_enabled') ? 'success' : 'warning'),
        ];
    }
}
