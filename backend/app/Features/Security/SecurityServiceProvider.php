<?php

namespace App\Features\Security;

use App\Features\Security\AntiCheat\Contracts\AnomalyPredictor;
use App\Features\Security\AntiCheat\Contracts\BehaviorPredictor;
use App\Features\Security\AntiCheat\Contracts\BotPredictor;
use App\Features\Security\AntiCheat\Contracts\FraudPredictor;
use App\Features\Security\AntiCheat\Predictors\NullAnomalyPredictor;
use App\Features\Security\AntiCheat\Predictors\NullBehaviorPredictor;
use App\Features\Security\AntiCheat\Predictors\NullBotPredictor;
use App\Features\Security\AntiCheat\Predictors\NullFraudPredictor;
use App\Features\Security\AntiCheat\ScannerRegistry;
use App\Features\Security\Console\PurgeSecurityDataCommand;
use App\Features\Security\Console\ReloadSecurityRulesCommand;
use App\Features\Security\Console\SecurityStatusCommand;
use App\Features\Security\Engine\ModerationEngine;
use App\Features\Security\Engine\RealtimeSecurityEngine;
use App\Features\Security\Engine\RiskEngine;
use App\Features\Security\Engine\SecurityEngine;
use App\Features\Security\Listeners\CaptureDomainEventForSecurity;
use App\Features\Security\Models\SecurityEvent;
use App\Features\Security\Policies\SecurityPolicy;
use App\Features\Security\Repositories\Contracts\SecurityRepository;
use App\Features\Security\Repositories\Eloquent\EloquentSecurityRepository;
use App\Features\Security\Rules\RuleProvider;
use App\Features\Security\Rules\SecurityRuleEngine;
use App\Features\Security\Services\SecurityAdminService;
use App\Features\Security\Services\SecurityAuditLogger;
use App\Features\Security\Services\SecurityCollector;
use App\Features\Security\Services\SecurityDeviceService;
use App\Features\Security\Services\SecurityNetworkService;
use App\Features\Security\Services\SecurityQueryService;
use App\Features\Security\Services\SecuritySessionService;
use App\Features\Security\Support\RouteRegistrar;
use App\Features\Security\Support\SecurityEventNormalizer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the anti-cheat platform.
 *
 * The only touch point with the rest of Civin is a wildcard event listener —
 * no existing module, route or migration is modified.
 */
final class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/security_platform.php', 'security_platform');

        $this->app->singleton(SecurityEventNormalizer::class);
        $this->app->singleton(SecurityRepository::class, EloquentSecurityRepository::class);
        $this->app->singleton(SecurityAuditLogger::class);
        $this->app->singleton(ScannerRegistry::class);
        $this->app->singleton(RuleProvider::class);
        $this->app->singleton(SecurityRuleEngine::class);
        $this->app->singleton(RiskEngine::class);
        $this->app->singleton(RealtimeSecurityEngine::class);
        $this->app->singleton(ModerationEngine::class);
        $this->app->singleton(SecurityEngine::class);
        $this->app->singleton(SecurityCollector::class);
        $this->app->singleton(SecurityDeviceService::class);
        $this->app->singleton(SecuritySessionService::class);
        $this->app->singleton(SecurityNetworkService::class);
        $this->app->singleton(SecurityQueryService::class);
        $this->app->singleton(SecurityAdminService::class);

        $this->bindPredictors();
    }

    public function boot(): void
    {
        Gate::policy(SecurityEvent::class, SecurityPolicy::class);

        // Observe every domain event without any module knowing we exist.
        Event::listen('*', CaptureDomainEventForSecurity::class);

        RouteRegistrar::register();
        RouteRegistrar::registerChannels();

        if ($this->app->runningInConsole()) {
            $this->commands([
                PurgeSecurityDataCommand::class,
                ReloadSecurityRulesCommand::class,
                SecurityStatusCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('security:purge')->dailyAt('03:30')->withoutOverlapping();
        });
    }

    /**
     * ML seams. Point any of these at a real model in config and the engine
     * starts consuming it — no engine code changes.
     */
    private function bindPredictors(): void
    {
        $defaults = [
            'fraud' => [FraudPredictor::class, NullFraudPredictor::class],
            'behavior' => [BehaviorPredictor::class, NullBehaviorPredictor::class],
            'bot' => [BotPredictor::class, NullBotPredictor::class],
            'anomaly' => [AnomalyPredictor::class, NullAnomalyPredictor::class],
        ];

        foreach ($defaults as $key => [$contract, $fallback]) {
            $this->app->singleton($contract, function () use ($key, $contract, $fallback) {
                $configured = config("security_platform.predictors.{$key}");

                if (is_string($configured) && class_exists($configured)) {
                    $instance = $this->app->make($configured);
                    if ($instance instanceof $contract) {
                        return $instance;
                    }
                }

                return $this->app->make($fallback);
            });
        }
    }
}
