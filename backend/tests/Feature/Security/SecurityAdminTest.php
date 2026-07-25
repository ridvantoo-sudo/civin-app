<?php

namespace Tests\Feature\Security;

use App\Features\Admin\Support\AdminRole;
use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Models\SecurityLog;
use App\Features\Security\Models\SecurityReview;
use App\Features\Security\Models\SecurityRule;
use App\Features\Security\Rules\RuleProvider;
use App\Features\Security\Services\SecurityAdminService;
use App\Features\Security\Support\SecurityAction;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Users\Models\User;
use Carbon\CarbonImmutable;
use Tests\Support\CreatesAdminUsers;

final class SecurityAdminTest extends SecurityTestCase
{
    use CreatesAdminUsers;

    public function test_super_admin_can_open_every_security_page(): void
    {
        $admin = $this->makeAdmin(AdminRole::SUPER_ADMIN);

        foreach ([
            '/admin/security',
            '/admin/security/threats',
            '/admin/security/fraud',
            '/admin/security/devices',
            '/admin/security/risk',
            '/admin/security-alerts',
            '/admin/security-bans',
            '/admin/security-reviews',
            '/admin/security-rules',
            '/admin/security-logs',
            '/admin/security-devices',
        ] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_moderator_can_open_the_security_dashboard(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);

        $this->actingAs($admin)->get('/admin/security')->assertOk();
    }

    public function test_finance_admin_cannot_open_the_security_dashboard(): void
    {
        $admin = $this->makeAdmin(AdminRole::FINANCE_ADMIN);

        $this->actingAs($admin)->get('/admin/security')->assertForbidden();
    }

    public function test_a_player_cannot_open_any_security_page(): void
    {
        $player = User::factory()->create([
            'is_guest' => false,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($player)->get('/admin/security')->assertForbidden();
    }

    public function test_acknowledging_an_alert_is_audited(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);
        $alert = SecurityAlert::query()->create([
            'user_id' => User::factory()->create()->id,
            'signal' => SecuritySignal::BOT_PLAY,
            'title' => 'Bot play',
            'severity' => SecuritySeverity::HIGH,
            'surface' => 'games',
            'action' => SecurityAction::REVIEW,
            'status' => SecurityAlert::STATUS_OPEN,
            'confidence' => 90,
        ]);

        app(SecurityAdminService::class)->acknowledgeAlert($alert, (string) $admin->id);

        $this->assertSame(SecurityAlert::STATUS_ACKNOWLEDGED, $alert->fresh()->status);
        $this->assertDatabaseHas('security_logs', [
            'channel' => SecurityLog::CHANNEL_ADMIN,
            'action' => 'alert.acknowledged',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_issuing_and_lifting_a_ban_is_audited(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);
        $player = User::factory()->create();
        $service = app(SecurityAdminService::class);

        $ban = $service->issueBan(
            (string) $player->id,
            SecurityBan::SCOPE_WALLET,
            SecurityAction::SUSPEND,
            'Manual review confirmed recharge fraud',
            (string) $admin->id,
            CarbonImmutable::now()->addDay(),
        );

        $this->assertFalse($ban->is_automatic);
        $this->assertDatabaseHas('security_logs', [
            'action' => 'ban.issued',
            'actor_id' => $admin->id,
            'user_id' => $player->id,
        ]);

        $service->liftBan($ban, (string) $admin->id, 'Appealed successfully');

        $this->assertSame(SecurityBan::STATUS_LIFTED, $ban->fresh()->status);
        $this->assertDatabaseHas('security_logs', ['action' => 'ban.lifted']);
    }

    public function test_completing_a_review_records_the_resolution(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);
        $review = SecurityReview::query()->create([
            'user_id' => User::factory()->create()->id,
            'queue' => 'wallet',
            'reason' => 'Suspicious recharge pattern',
            'status' => SecurityReview::STATUS_PENDING,
            'priority' => 'normal',
            'risk_score' => 80,
        ]);

        $service = app(SecurityAdminService::class);
        $service->claimReview($review, (string) $admin->id);
        $service->completeReview(
            $review,
            SecurityReview::RESOLUTION_CLEARED,
            (string) $admin->id,
            'False positive — VIP whale behaviour.',
        );

        $review->refresh();
        $this->assertSame(SecurityReview::STATUS_COMPLETED, $review->status);
        $this->assertSame(SecurityReview::RESOLUTION_CLEARED, $review->resolution);
        $this->assertSame((string) $admin->id, $review->reviewed_by);
    }

    public function test_editing_a_rule_hot_reloads_and_is_audited(): void
    {
        $admin = $this->makeAdmin(AdminRole::SUPER_ADMIN);

        $rule = SecurityRule::query()->create([
            'key' => 'tests.admin_rule',
            'name' => 'Admin rule',
            'signal' => 'tests.admin_rule',
            'severity' => SecuritySeverity::HIGH,
            'action' => SecurityAction::WARN,
            'threshold' => 70,
            'priority' => 50,
            'is_enabled' => true,
        ]);

        app(SecurityAdminService::class)->ruleChanged($rule, (string) $admin->id, 'updated');

        $this->assertDatabaseHas('security_logs', [
            'action' => 'rule.updated',
            'actor_id' => $admin->id,
        ]);
        $this->assertNotNull(app(RuleProvider::class)->find('tests.admin_rule'));
    }

    public function test_security_logs_are_append_only_from_the_panel(): void
    {
        $admin = $this->makeAdmin(AdminRole::SUPER_ADMIN);

        $this->actingAs($admin)
            ->get('/admin/security-logs')
            ->assertOk()
            ->assertDontSee('Create');
    }
}
