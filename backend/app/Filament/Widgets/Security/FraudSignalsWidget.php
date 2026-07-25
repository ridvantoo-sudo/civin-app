<?php

namespace App\Filament\Widgets\Security;

use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityFlag;
use App\Features\Security\Models\SecurityScore;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;
use App\Filament\Widgets\Security\Concerns\SecurityWidget;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FraudSignalsWidget extends StatsOverviewWidget
{
    use SecurityWidget;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $since = CarbonImmutable::now()->subDays(7);

        $walletFraud = SecurityAlert::query()
            ->where('created_at', '>=', $since)
            ->whereIn('signal', [SecuritySignal::WALLET_FRAUD, SecuritySignal::WALLET_ABUSE])
            ->count();

        $farming = SecurityAlert::query()
            ->where('created_at', '>=', $since)
            ->whereIn('signal', [SecuritySignal::REWARD_FARMING, SecuritySignal::REFERRAL_ABUSE])
            ->count();

        $replay = SecurityAlert::query()
            ->where('created_at', '>=', $since)
            ->whereIn('signal', [SecuritySignal::REPLAY_ATTACK, SecuritySignal::TAMPERED_REQUEST])
            ->count();

        $lockedWallets = SecurityFlag::query()
            ->active()
            ->where('surface', SecuritySurface::WALLET)
            ->distinct()
            ->count('user_id');

        $avgFraudScore = (float) SecurityScore::query()->where('fraud_score', '>', 0)->avg('fraud_score');

        return [
            Stat::make('Wallet fraud signals', number_format($walletFraud))
                ->description('Recharge, withdrawal and transfer abuse (7d)')
                ->icon('heroicon-o-banknotes')
                ->color($walletFraud > 0 ? 'danger' : 'success'),
            Stat::make('Farming signals', number_format($farming))
                ->description('Coin, gift and referral farming (7d)')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color($farming > 0 ? 'warning' : 'success'),
            Stat::make('Replay / tampering', number_format($replay))
                ->description('Duplicate settlements and forged payloads (7d)')
                ->icon('heroicon-o-document-duplicate')
                ->color($replay > 0 ? 'danger' : 'success'),
            Stat::make('Wallet-flagged accounts', number_format($lockedWallets))
                ->description('Standing wallet-surface flags')
                ->icon('heroicon-o-lock-closed')
                ->color($lockedWallets > 0 ? 'warning' : 'gray'),
            Stat::make('Mean fraud score', number_format($avgFraudScore, 1))
                ->description('Across accounts with any fraud signal')
                ->icon('heroicon-o-chart-bar')
                ->color($avgFraudScore >= 60 ? 'danger' : 'gray'),
        ];
    }
}
