<?php

namespace App\Features\Growth;

use App\Features\Growth\Console\DispatchDueCampaignsCommand;
use App\Features\Growth\Contracts\PushGateway;
use App\Features\Growth\Gateways\FcmPushGateway;
use App\Features\Growth\Models\GrowthCampaign;
use App\Features\Growth\Models\Invite;
use App\Features\Growth\Models\PromoCode;
use App\Features\Growth\Models\ReferralCode;
use App\Features\Growth\Policies\GrowthPolicy;
use App\Features\Growth\Services\CampaignService;
use App\Features\Growth\Services\InviteService;
use App\Features\Growth\Services\PromoCodeService;
use App\Features\Growth\Services\ReferralService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Growth platform: referrals, invite rewards, promo/discount codes, and
 * push/email campaigns. Self-contained — only extends Wallet via the shared
 * applyGrowthReward seam for reward credits.
 */
final class GrowthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/growth.php', 'growth');

        $this->app->singleton(PushGateway::class, FcmPushGateway::class);
        $this->app->singleton(ReferralService::class);
        $this->app->singleton(InviteService::class);
        $this->app->singleton(PromoCodeService::class);
        $this->app->singleton(CampaignService::class);
    }

    public function boot(): void
    {
        Gate::policy(ReferralCode::class, GrowthPolicy::class);
        Gate::policy(Invite::class, GrowthPolicy::class);
        Gate::policy(PromoCode::class, GrowthPolicy::class);
        Gate::policy(GrowthCampaign::class, GrowthPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([DispatchDueCampaignsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('growth:dispatch-campaigns')->everyMinute()->withoutOverlapping();
        });
    }
}
