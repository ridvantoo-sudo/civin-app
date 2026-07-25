<?php

namespace Tests\Feature\Security;

use App\Features\Admin\Support\AdminRole;
use App\Features\Security\AntiCheat\Contracts\Scanner;
use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Engine\RealtimeSecurityEngine;
use App\Features\Security\Engine\SecurityEngine;
use App\Features\Security\Events\AccountFlagged;
use App\Features\Security\Events\AccountSuspended;
use App\Features\Security\Events\FraudDetected;
use App\Features\Security\Events\GameBlocked;
use App\Features\Security\Events\RiskScoreChanged;
use App\Features\Security\Events\SecurityAlertRaised;
use App\Features\Security\Events\WalletLocked;
use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Support\SecurityAction;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecurityPermission;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;
use App\Features\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Tests\Support\CreatesAdminUsers;

final class SecurityRealtimeTest extends SecurityTestCase
{
    use CreatesAdminUsers;

    public function test_a_detection_broadcasts_an_alert_and_a_flag(): void
    {
        Event::fake([SecurityAlertRaised::class, AccountFlagged::class, RiskScoreChanged::class]);
        $user = User::factory()->create();

        $this->detect($user, SecuritySignal::BOT_PLAY, SecuritySeverity::HIGH, 92.0);

        Event::assertDispatched(SecurityAlertRaised::class);
        Event::assertDispatched(AccountFlagged::class);
        Event::assertDispatched(RiskScoreChanged::class);
    }

    public function test_critical_findings_broadcast_fraud_detected(): void
    {
        Event::fake([FraudDetected::class]);
        $user = User::factory()->create();

        $this->detect($user, SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 97.0);

        Event::assertDispatched(
            FraudDetected::class,
            static fn (FraudDetected $event): bool => in_array(SecuritySignal::WALLET_FRAUD, $event->signals, true),
        );
    }

    public function test_a_wallet_suspension_broadcasts_wallet_locked(): void
    {
        Event::fake([AccountSuspended::class, WalletLocked::class]);
        $user = User::factory()->create();

        app(RealtimeSecurityEngine::class)->accountSuspended($this->ban($user, SecurityBan::SCOPE_WALLET));

        Event::assertDispatched(AccountSuspended::class);
        Event::assertDispatched(WalletLocked::class);
        Event::assertNotDispatched(GameBlocked::class);
    }

    public function test_a_games_suspension_broadcasts_game_blocked(): void
    {
        Event::fake([GameBlocked::class, WalletLocked::class]);
        $user = User::factory()->create();

        app(RealtimeSecurityEngine::class)->accountSuspended($this->ban($user, SecurityBan::SCOPE_GAMES));

        Event::assertDispatched(GameBlocked::class);
        Event::assertNotDispatched(WalletLocked::class);
    }

    public function test_a_platform_ban_locks_both_wallet_and_games(): void
    {
        Event::fake([GameBlocked::class, WalletLocked::class]);
        $user = User::factory()->create();

        app(RealtimeSecurityEngine::class)->accountSuspended($this->ban($user, SecurityBan::SCOPE_PLATFORM));

        Event::assertDispatched(WalletLocked::class);
        Event::assertDispatched(GameBlocked::class);
    }

    public function test_alerts_broadcast_to_the_monitor_and_to_the_affected_account(): void
    {
        $user = User::factory()->create();
        $alert = SecurityAlert::query()->create([
            'user_id' => $user->id,
            'signal' => SecuritySignal::BOT_PLAY,
            'title' => 'Bot play',
            'severity' => SecuritySeverity::HIGH,
            'surface' => SecuritySurface::GAMES,
            'action' => SecurityAction::REVIEW,
            'status' => SecurityAlert::STATUS_OPEN,
            'confidence' => 90,
        ]);

        $channels = array_map(
            static fn (PrivateChannel $channel): string => $channel->name,
            (new SecurityAlertRaised($alert))->broadcastOn(),
        );

        $this->assertContains('private-security.monitor', $channels);
        $this->assertContains('private-user.security.'.$user->id, $channels);
    }

    public function test_identical_alerts_are_throttled_so_the_monitor_cannot_flood(): void
    {
        config(['security_platform.realtime.broadcast_throttle_seconds' => 60]);
        Event::fake([SecurityAlertRaised::class]);

        $user = User::factory()->create();
        $realtime = app(RealtimeSecurityEngine::class);

        for ($i = 0; $i < 5; $i++) {
            $realtime->alertRaised(SecurityAlert::query()->create([
                'user_id' => $user->id,
                'signal' => SecuritySignal::BOT_PLAY,
                'title' => 'Bot play',
                'severity' => SecuritySeverity::HIGH,
                'surface' => SecuritySurface::GAMES,
                'action' => SecurityAction::REVIEW,
                'status' => SecurityAlert::STATUS_OPEN,
                'confidence' => 90,
            ]));
        }

        Event::assertDispatchedTimes(SecurityAlertRaised::class, 1);
    }

    public function test_only_privileged_staff_may_join_the_monitor_channel(): void
    {
        $player = User::factory()->create(['is_guest' => false, 'status' => User::STATUS_ACTIVE]);
        $moderator = $this->makeAdmin(AdminRole::MODERATOR);

        $this->assertFalse(SecurityPermission::allows($player, SecurityPermission::VIEW_SECURITY));
        $this->assertTrue(SecurityPermission::allows($moderator, SecurityPermission::VIEW_SECURITY));
    }

    private function ban(User $user, string $scope): SecurityBan
    {
        return SecurityBan::query()->create([
            'user_id' => $user->id,
            'scope' => $scope,
            'action' => SecurityAction::SUSPEND,
            'reason' => 'test',
            'status' => SecurityBan::STATUS_ACTIVE,
            'is_automatic' => true,
            'starts_at' => CarbonImmutable::now(),
        ]);
    }

    private function detect(User $user, string $signal, string $severity, float $confidence): void
    {
        $engine = app(SecurityEngine::class);

        $engine->registerScanner(new class($signal, $severity, $confidence) implements Scanner
        {
            public function __construct(
                private readonly string $signal,
                private readonly string $severity,
                private readonly float $confidence,
            ) {}

            public function key(): string
            {
                return 'tests.realtime';
            }

            public function supports(SecurityEventData $event): bool
            {
                return true;
            }

            public function scan(SecurityEventData $event, SecurityContext $context): array
            {
                return [DetectedSignal::make(
                    signal: $this->signal,
                    severity: $this->severity,
                    confidence: $this->confidence,
                    title: 'Injected detection',
                    surface: $event->surface,
                    userId: $event->userId,
                )];
            }
        });

        $engine->analyze($this->event('anything_happened', [], [
            'user_id' => (string) $user->id,
            'surface' => SecuritySurface::WALLET,
        ]));
    }
}
