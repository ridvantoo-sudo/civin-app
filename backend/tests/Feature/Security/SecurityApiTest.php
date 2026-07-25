<?php

namespace Tests\Feature\Security;

use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Models\SecurityDevice;
use App\Features\Security\Models\SecurityReview;
use App\Features\Security\Models\SecuritySession;
use App\Features\Security\Support\SecurityAction;
use App\Features\Users\Models\User;
use Carbon\CarbonImmutable;

final class SecurityApiTest extends SecurityTestCase
{
    public function test_all_security_endpoints_require_authentication(): void
    {
        foreach ([
            ['get', '/api/v1/security/me'],
            ['get', '/api/v1/security/risk'],
            ['get', '/api/v1/security/devices'],
            ['get', '/api/v1/security/history'],
            ['get', '/api/v1/security/rules'],
            ['post', '/api/v1/security/report'],
            ['post', '/api/v1/security/device/verify'],
            ['post', '/api/v1/security/session/verify'],
        ] as [$method, $uri]) {
            $this->json($method, $uri)->assertUnauthorized();
        }
    }

    public function test_me_returns_the_callers_own_posture(): void
    {
        $user = $this->actingUser();

        $this->getJson('/api/v1/security/me')
            ->assertOk()
            ->assertJsonPath('data.user_id', (string) $user->id)
            ->assertJsonPath('data.wallet_locked', false)
            ->assertJsonPath('data.games_blocked', false)
            ->assertJsonStructure([
                'data' => [
                    'user_id',
                    'scores' => ['security', 'trust', 'risk', 'fraud', 'bot', 'band'],
                    'restrictions',
                    'active_flag_count',
                    'open_alert_count',
                    'device_count',
                    'active_session_count',
                ],
            ]);
    }

    public function test_me_reports_an_active_wallet_restriction(): void
    {
        $user = $this->actingUser();

        SecurityBan::query()->create([
            'user_id' => $user->id,
            'scope' => SecurityBan::SCOPE_WALLET,
            'action' => SecurityAction::SUSPEND,
            'reason' => 'Recharge fraud',
            'status' => SecurityBan::STATUS_ACTIVE,
            'is_automatic' => true,
            'starts_at' => CarbonImmutable::now(),
        ]);

        $this->getJson('/api/v1/security/me')
            ->assertOk()
            ->assertJsonPath('data.wallet_locked', true)
            ->assertJsonPath('data.games_blocked', false);
    }

    public function test_risk_returns_scores_flags_and_a_recommended_action(): void
    {
        $this->actingUser();

        $this->getJson('/api/v1/security/risk')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['user_id', 'scores', 'band', 'breakdown', 'flags', 'recommended_action'],
            ]);
    }

    public function test_devices_only_ever_lists_the_callers_own_devices(): void
    {
        $user = $this->actingUser();
        $stranger = User::factory()->create();

        $this->postJson('/api/v1/security/device/verify', [
            'device_id' => 'mine-1',
            'platform' => 'android',
        ])->assertOk();

        SecurityDevice::query()->create([
            'user_id' => $stranger->id,
            'device_hash' => hash('sha256', 'not-mine'),
            'seen_count' => 1,
        ]);

        $response = $this->getJson('/api/v1/security/devices')->assertOk();

        $this->assertCount(1, $response->json('data.devices'));
    }

    public function test_history_returns_events_and_alerts_and_honours_the_limit(): void
    {
        $this->actingUser();

        $this->getJson('/api/v1/security/history?limit=10')
            ->assertOk()
            ->assertJsonStructure(['data' => ['events', 'alerts']]);

        $this->getJson('/api/v1/security/history?limit=9999')
            ->assertUnprocessable();
    }

    public function test_rules_exposes_metadata_but_no_detector_internals(): void
    {
        $this->actingUser();

        $response = $this->getJson('/api/v1/security/rules')->assertOk();
        $rule = $response->json('data.rules.0');

        $this->assertArrayHasKey('key', $rule);
        $this->assertArrayHasKey('action', $rule);
        $this->assertArrayNotHasKey('conditions', $rule);
        $this->assertArrayNotHasKey('threshold', $rule);
    }

    public function test_report_opens_a_manual_review_case(): void
    {
        $reporter = $this->actingUser();
        $suspect = User::factory()->create();

        $this->postJson('/api/v1/security/report', [
            'reported_user_id' => (string) $suspect->id,
            'category' => 'bot',
            'surface' => 'games',
            'reason' => 'Plays perfectly for hours',
            'description' => 'Never misses a round.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', SecurityReview::STATUS_PENDING);

        $review = SecurityReview::query()->firstOrFail();
        $this->assertSame((string) $suspect->id, $review->user_id);
        $this->assertSame((string) $reporter->id, $review->context['reporter_id']);
    }

    public function test_report_validates_its_input(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/security/report', ['category' => 'nonsense'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category', 'reason']);
    }

    public function test_device_verify_records_attestation_and_flags_compromise(): void
    {
        $user = $this->actingUser();

        $this->postJson('/api/v1/security/device/verify', [
            'device_id' => 'device-xyz',
            'platform' => 'android',
            'model' => 'Pixel 8',
            'os_version' => '15',
            'app_version' => '2.4.0',
            'is_rooted' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.integrity_compromised', true)
            ->assertJsonPath('data.blocked', false);

        $device = SecurityDevice::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($device->is_rooted);
        $this->assertGreaterThan(0.0, (float) $device->risk_score);

        // Only hashes are persisted, never the raw identifier.
        $this->assertNotSame('device-xyz', $device->device_hash);
        $this->assertStringNotContainsString('device-xyz', json_encode($device->fingerprint));
    }

    public function test_device_verify_is_idempotent_for_the_same_device(): void
    {
        $user = $this->actingUser();

        $payload = ['device_id' => 'stable-device', 'platform' => 'ios'];
        $this->postJson('/api/v1/security/device/verify', $payload)->assertOk();
        $this->postJson('/api/v1/security/device/verify', $payload)->assertOk();

        $this->assertSame(1, SecurityDevice::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, SecurityDevice::query()->where('user_id', $user->id)->value('seen_count'));
    }

    public function test_session_verify_reports_concurrency(): void
    {
        $user = $this->actingUser();

        $this->postJson('/api/v1/security/session/verify', [
            'session_id' => 'session-1',
            'device_id' => 'device-1',
            'platform' => 'android',
        ])
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.concurrent_sessions', 1);

        foreach (['session-2', 'session-3', 'session-4'] as $session) {
            $this->postJson('/api/v1/security/session/verify', ['session_id' => $session]);
        }

        $response = $this->postJson('/api/v1/security/session/verify', ['session_id' => 'session-5'])->assertOk();

        $this->assertFalse($response->json('data.verified'));
        $this->assertGreaterThan(
            (int) config('security_platform.thresholds.sessions.max_concurrent'),
            $response->json('data.concurrent_sessions'),
        );
        $this->assertSame(5, SecuritySession::query()->where('user_id', $user->id)->count());
    }

    public function test_session_verify_validates_its_input(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/security/session/verify', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session_id']);
    }

    public function test_the_api_never_leaks_another_accounts_state(): void
    {
        $stranger = User::factory()->create();
        SecurityBan::query()->create([
            'user_id' => $stranger->id,
            'scope' => SecurityBan::SCOPE_PLATFORM,
            'action' => SecurityAction::BAN,
            'reason' => 'Stranger ban',
            'status' => SecurityBan::STATUS_ACTIVE,
            'is_automatic' => true,
            'starts_at' => CarbonImmutable::now(),
        ]);

        $this->actingUser();

        $response = $this->getJson('/api/v1/security/me')->assertOk();

        $this->assertSame([], $response->json('data.restrictions'));
        $this->assertStringNotContainsString('Stranger ban', $response->getContent());
    }
}
