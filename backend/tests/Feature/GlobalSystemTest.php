<?php

namespace Tests\Feature;

use App\Features\Global\Models\TimezoneSchedule;
use App\Features\Global\Support\ComplianceAction;
use App\Features\Global\Support\TimezoneScheduleStatus;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GlobalSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_global_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/global/compliance/evaluate?country_code=IQ')->assertUnauthorized();
        $this->getJson('/api/v1/global/timezone')->assertUnauthorized();
        $this->getJson('/api/v1/global/cdn/config')->assertUnauthorized();
    }

    public function test_compliance_evaluate_matches_seeded_rules(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/global/compliance/evaluate?country_code=IQ&feature=live&age=20')
            ->assertOk()
            ->assertJsonPath('data.action', ComplianceAction::ALLOW)
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.country_code', 'IQ');

        $this->getJson('/api/v1/global/compliance/evaluate?country_code=IQ&region_code=IQ-KRI&feature=games&age=14')
            ->assertOk()
            ->assertJsonPath('data.action', ComplianceAction::RESTRICT)
            ->assertJsonPath('data.age_ok', false);

        $this->getJson('/api/v1/global/compliance/evaluate?country_code=CN&feature=payments&age=30')
            ->assertOk()
            ->assertJsonPath('data.action', ComplianceAction::DENY)
            ->assertJsonPath('data.allowed', false);
    }

    public function test_timezone_preference_convert_and_schedule(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/global/timezones')
            ->assertOk()
            ->assertJsonFragment(['id' => 'Asia/Baghdad']);

        $this->putJson('/api/v1/global/timezone', ['timezone' => 'Asia/Baghdad'])
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Baghdad')
            ->assertJsonPath('data.is_default', false);

        $converted = $this->postJson('/api/v1/global/timezone/convert', [
            'local' => '2026-08-13 18:00:00',
            'timezone' => 'Asia/Baghdad',
        ])->assertOk()
            ->json('data');

        $this->assertSame('Asia/Baghdad', $converted['timezone']);
        $this->assertNotEmpty($converted['utc']);

        $id = $this->postJson('/api/v1/global/timezone/schedules', [
            'name' => 'Promo blast',
            'local' => '2026-08-13 18:00:00',
            'timezone' => 'Asia/Baghdad',
            'payload' => ['channel' => 'push'],
        ])->assertCreated()
            ->assertJsonPath('data.status', TimezoneScheduleStatus::SCHEDULED)
            ->json('data.id');

        $this->assertDatabaseHas('timezone_schedules', [
            'id' => $id,
            'user_id' => $user->id,
            'name' => 'Promo blast',
        ]);

        $this->getJson('/api/v1/global/timezone/schedules')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);
    }

    public function test_cdn_config_and_resolve(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/global/cdn/config?country_code=IQ')
            ->assertOk()
            ->assertJsonPath('data.base_url', 'https://cdn.civin.app')
            ->assertJsonPath('data.edge', 'me-central-1');

        $this->postJson('/api/v1/global/cdn/resolve', [
            'path' => 'avatars/user.png',
            'country_code' => 'IQ',
            'image_optimize' => true,
        ])->assertOk()
            ->assertJsonPath('data.path', '/avatars/user.png')
            ->assertJsonFragment(['base_url' => 'https://cdn.civin.app']);

        $url = $this->postJson('/api/v1/global/cdn/resolve', [
            'path' => 'avatars/user.png',
            'image_optimize' => true,
        ])->json('data.url');

        $this->assertStringStartsWith('https://cdn.civin.app/avatars/user.png?', $url);
        $this->assertStringContainsString('format=webp', $url);
    }

    public function test_process_due_schedules_command(): void
    {
        $user = User::factory()->create();
        TimezoneSchedule::query()->create([
            'user_id' => $user->id,
            'name' => 'Past job',
            'run_at_utc' => now('UTC')->subMinute(),
            'timezone' => 'UTC',
            'local_wall_time' => now('UTC')->subMinute()->format('Y-m-d H:i:s'),
            'status' => TimezoneScheduleStatus::SCHEDULED,
        ]);

        $this->artisan('global:process-schedules')->assertSuccessful();

        $this->assertDatabaseHas('timezone_schedules', [
            'name' => 'Past job',
            'status' => TimezoneScheduleStatus::DONE,
        ]);
    }
}
