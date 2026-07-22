<?php

namespace Tests\Feature;

use App\Features\LiveChat\Events\ViewerJoined;
use App\Features\LiveChat\Events\ViewerLeft;
use App\Features\LiveStreaming\Events\LiveEnded;
use App\Features\LiveStreaming\Events\LiveStarted;
use App\Features\LiveStreaming\Models\LiveCategory;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveSession;
use App\Features\LiveStreaming\Services\AgoraService;
use App\Features\Users\Models\User;
use App\Features\UserStatus\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveStreamingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('agora.app_id', str_repeat('a', 32));
        config()->set('agora.app_certificate', str_repeat('b', 32));
        config()->set('agora.token_ttl', 3600);
    }

    public function test_all_live_endpoints_require_authentication(): void
    {
        $room = LiveRoom::factory()->create();

        $this->postJson('/api/v1/live/create')->assertUnauthorized();
        $this->postJson("/api/v1/live/{$room->id}/start")->assertUnauthorized();
        $this->postJson("/api/v1/live/{$room->id}/end")->assertUnauthorized();
        $this->getJson('/api/v1/live')->assertUnauthorized();
        $this->getJson("/api/v1/live/{$room->id}")->assertUnauthorized();
        $this->postJson("/api/v1/live/{$room->id}/join")->assertUnauthorized();
        $this->postJson("/api/v1/live/{$room->id}/leave")->assertUnauthorized();
    }

    public function test_host_can_create_and_start_room_with_v2_token(): void
    {
        Event::fake([LiveStarted::class]);
        $host = User::factory()->create();
        $category = LiveCategory::factory()->create();
        Sanctum::actingAs($host);

        $response = $this->postJson('/api/v1/live/create', [
            'category_id' => $category->id,
            'title' => 'Production stream',
            'description' => 'Streaming now',
            'thumbnail' => 'https://cdn.example.com/live.jpg',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'created');

        $roomId = $response->json('data.id');
        $startResponse = $this->postJson("/api/v1/live/{$roomId}/start")
            ->assertOk()
            ->assertJsonPath('data.room.status', 'live')
            ->assertJsonPath('data.rtc.app_id', str_repeat('a', 32))
            ->assertJsonPath('data.rtc.channel', LiveRoom::findOrFail($roomId)->agora_channel_name)
            ->assertJsonPath('data.rtc.uid', LiveRoom::findOrFail($roomId)->stream_uid)
            ->assertJson(fn ($json) => $json->whereType('data.rtc.token', 'string')
                ->whereType('data.rtc.expires_at', 'string')
                ->etc());

        $this->assertSame('007', substr((string) $startResponse->json('data.rtc.token'), 0, 3));
        $this->assertDatabaseHas('live_sessions', ['room_id' => $roomId, 'peak_viewers' => 0]);
        $this->assertDatabaseHas('user_status', ['user_id' => $host->id, 'is_live' => true]);
        Event::assertDispatched(LiveStarted::class);
    }

    public function test_inactive_categories_and_non_owner_lifecycle_actions_are_rejected(): void
    {
        $host = User::factory()->create();
        $other = User::factory()->create();
        $inactive = LiveCategory::factory()->create(['status' => 'inactive']);
        $room = LiveRoom::factory()->create(['host_id' => $host->id]);
        Sanctum::actingAs($other);

        $this->postJson('/api/v1/live/create', [
            'category_id' => $inactive->id,
            'title' => 'Rejected room',
        ])->assertUnprocessable();
        $this->postJson("/api/v1/live/{$room->id}/start")->assertForbidden();

        $room->update(['status' => 'live', 'started_at' => now()]);
        LiveSession::query()->create(['room_id' => $room->id]);
        $this->postJson("/api/v1/live/{$room->id}/end")->assertForbidden();
    }

    public function test_join_and_leave_are_idempotent_and_track_peak(): void
    {
        Event::fake([ViewerJoined::class, ViewerLeft::class]);
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $room = $this->liveRoom($host);
        Sanctum::actingAs($viewer);

        $first = $this->postJson("/api/v1/live/{$room->id}/join")
            ->assertOk()
            ->assertJsonPath('data.room.viewer_count', 1)
            ->assertJsonPath('data.rtc.app_id', str_repeat('a', 32));
        $viewerUid = $first->json('data.rtc.uid');

        $this->postJson("/api/v1/live/{$room->id}/join")
            ->assertOk()
            ->assertJsonPath('data.room.viewer_count', 1)
            ->assertJsonPath('data.rtc.uid', $viewerUid);

        $this->assertDatabaseCount('live_viewers', 1);
        $this->assertSame(1, $room->session->fresh()->peak_viewers);
        Event::assertDispatchedTimes(ViewerJoined::class, 1);

        $this->postJson("/api/v1/live/{$room->id}/leave")
            ->assertOk()
            ->assertJsonPath('data.viewer_count', 0);
        $this->postJson("/api/v1/live/{$room->id}/leave")
            ->assertOk()
            ->assertJsonPath('data.viewer_count', 0);

        $this->assertNotNull($room->viewers()->firstOrFail()->left_at);
        Event::assertDispatchedTimes(ViewerLeft::class, 1);
    }

    public function test_host_cannot_join_and_non_live_room_cannot_be_joined(): void
    {
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $room = $this->liveRoom($host);

        Sanctum::actingAs($host);
        $this->postJson("/api/v1/live/{$room->id}/join")->assertUnprocessable();

        $room->update(['status' => 'ended', 'ended_at' => now()]);
        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/live/{$room->id}/join")->assertUnprocessable();
    }

    public function test_end_closes_viewers_records_duration_peak_and_rejects_repeat(): void
    {
        Event::fake([LiveEnded::class]);
        Carbon::setTestNow('2026-07-22 10:00:00');
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        UserStatus::factory()->create([
            'user_id' => $host->id,
            'is_live' => true,
            'live_started_at' => now()->subMinutes(2),
        ]);
        $room = $this->liveRoom($host, now()->subMinutes(2));

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/live/{$room->id}/join")->assertOk();

        Carbon::setTestNow('2026-07-22 10:02:05');
        Sanctum::actingAs($host);
        $this->postJson("/api/v1/live/{$room->id}/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended')
            ->assertJsonPath('data.viewer_count', 0);

        $session = $room->session->fresh();
        $this->assertSame(245, $session->duration);
        $this->assertSame(1, $session->peak_viewers);
        $this->assertNotNull($room->viewers()->firstOrFail()->left_at);
        $this->assertFalse($host->socialStatus->fresh()->is_live);
        Event::assertDispatched(LiveEnded::class);
        $this->postJson("/api/v1/live/{$room->id}/end")->assertUnprocessable();
        Carbon::setTestNow();
    }

    public function test_live_list_is_paginated_and_show_eager_loads_relations(): void
    {
        $user = User::factory()->create();
        $live = $this->liveRoom(User::factory()->create());
        LiveRoom::factory()->create(['status' => 'ended', 'started_at' => now(), 'ended_at' => now()]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/live?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $live->id)
            ->assertJsonStructure(['data', 'links', 'meta']);
        $this->getJson("/api/v1/live/{$live->id}")
            ->assertOk()
            ->assertJsonPath('data.host.id', $live->host_id)
            ->assertJsonPath('data.category.id', $live->category_id);
        $this->getJson('/api/v1/live?per_page=101')->assertUnprocessable();
    }

    public function test_agora_service_generates_deterministic_nonzero_v2_viewer_tokens_and_fails_closed(): void
    {
        $agora = app(AgoraService::class);
        $first = $agora->generateViewerToken('live_valid-channel', 'user-uuid');
        $second = $agora->generateViewerToken('live_valid-channel', 'user-uuid');

        $this->assertSame($first->uid, $second->uid);
        $this->assertGreaterThan(0, $first->uid);
        $this->assertLessThanOrEqual(4294967295, $first->uid);
        $this->assertStringStartsWith('007', $first->token);
        $this->assertTrue($agora->validateChannel($agora->createChannel('room-id')));
        $this->assertFalse($agora->validateChannel(str_repeat('a', 65)));

        config()->set('agora.app_certificate', '');
        $this->expectException(\RuntimeException::class);
        $agora->generateHostToken('live_valid-channel', 123);
    }

    private function liveRoom(User $host, ?Carbon $startedAt = null): LiveRoom
    {
        $startedAt ??= now();
        $room = LiveRoom::factory()->create([
            'host_id' => $host->id,
            'status' => 'live',
            'started_at' => $startedAt,
        ]);
        LiveSession::query()->create(['room_id' => $room->id]);

        return $room->fresh()->load('session');
    }
}
