<?php

namespace Tests\Feature;

use App\Features\CreatorTools\Jobs\ProcessStreamClipJob;
use App\Features\CreatorTools\Listeners\FinalizeCreatorToolsOnLiveEnd;
use App\Features\CreatorTools\Models\RtmpIngestCredential;
use App\Features\CreatorTools\Models\StreamAnalytics;
use App\Features\CreatorTools\Models\StreamClip;
use App\Features\CreatorTools\Models\StreamReplay;
use App\Features\CreatorTools\Models\StreamSchedule;
use App\Features\CreatorTools\Support\ClipKind;
use App\Features\CreatorTools\Support\MediaStatus;
use App\Features\CreatorTools\Support\ScheduleStatus;
use App\Features\LiveStreaming\Events\LiveEnded;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveSession;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreatorToolsSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        config()->set('creator_tools.clips.async_processing', true);
        config()->set('creator_tools.replays.min_duration_seconds', 30);
        config()->set('creator_tools.rtmp.enabled', true);
    }

    public function test_creator_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/creator/schedules')->assertUnauthorized();
        $this->getJson('/api/v1/creator/replays')->assertUnauthorized();
        $this->postJson('/api/v1/creator/rtmp')->assertUnauthorized();
        $this->getJson('/api/v1/creator/analytics/summary')->assertUnauthorized();
    }

    public function test_host_can_schedule_and_cancel_stream(): void
    {
        $host = User::factory()->create();
        Sanctum::actingAs($host);

        $id = $this->postJson('/api/v1/creator/schedules', [
            'title' => 'Tonight live',
            'scheduled_at' => now()->addHours(2)->toISOString(),
            'timezone' => 'Asia/Baghdad',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('stream_schedules', [
            'id' => $id,
            'status' => ScheduleStatus::SCHEDULED,
            'host_id' => $host->id,
        ]);

        $this->postJson("/api/v1/creator/schedules/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', ScheduleStatus::CANCELLED);
    }

    public function test_host_can_create_clip_and_highlight(): void
    {
        Bus::fake([ProcessStreamClipJob::class]);

        $host = User::factory()->create();
        $room = LiveRoom::factory()->create(['host_id' => $host->id, 'status' => 'live']);
        Sanctum::actingAs($host);

        $clipId = $this->postJson("/api/v1/creator/rooms/{$room->id}/clips", [
            'title' => 'Funny moment',
            'start_seconds' => 10,
            'end_seconds' => 25,
            'kind' => ClipKind::CLIP,
        ])->assertCreated()->json('data.id');

        Bus::assertDispatched(ProcessStreamClipJob::class);

        $this->postJson("/api/v1/creator/rooms/{$room->id}/clips", [
            'title' => 'Best gift',
            'start_seconds' => 40,
            'end_seconds' => 55,
            'kind' => ClipKind::HIGHLIGHT,
        ])->assertCreated()->assertJsonPath('data.kind', ClipKind::HIGHLIGHT);

        $this->assertSame(2, StreamClip::query()->where('room_id', $room->id)->count());
        $this->assertSame(MediaStatus::PROCESSING, StreamClip::query()->find($clipId)?->status);
    }

    public function test_rtmp_credentials_issued_for_obs(): void
    {
        $host = User::factory()->create();
        $room = LiveRoom::factory()->create(['host_id' => $host->id]);
        Sanctum::actingAs($host);

        $payload = $this->postJson('/api/v1/creator/rtmp', ['room_id' => $room->id])
            ->assertCreated()
            ->json('data');

        $this->assertNotEmpty($payload['ingest_url']);
        $this->assertNotEmpty($payload['stream_key']);
        $this->assertSame($payload['ingest_url'], $payload['obs_server']);
        $this->assertSame($payload['stream_key'], $payload['obs_stream_key']);

        $this->getJson('/api/v1/creator/rtmp?room_id='.$room->id)
            ->assertOk()
            ->assertJsonPath('data.stream_key', $payload['stream_key']);

        $this->postJson('/api/v1/creator/rtmp/'.$payload['id'].'/revoke')
            ->assertOk()
            ->assertJsonPath('data.status', RtmpIngestCredential::STATUS_REVOKED);
    }

    public function test_live_ended_finalizes_analytics_and_creates_replay(): void
    {
        $host = User::factory()->create();
        $room = LiveRoom::factory()->create([
            'host_id' => $host->id,
            'status' => 'ended',
            'title' => 'Big show',
            'started_at' => now()->subMinutes(10),
            'ended_at' => now(),
        ]);

        LiveSession::query()->create([
            'room_id' => $room->id,
            'duration' => 600,
            'peak_viewers' => 42,
            'created_at' => now(),
        ]);

        (new FinalizeCreatorToolsOnLiveEnd(
            $this->app->make(\App\Features\CreatorTools\Services\StreamAnalyticsService::class),
            $this->app->make(\App\Features\CreatorTools\Services\StreamReplayService::class),
        ))->handle(new LiveEnded($room->id, $host->id, 600));

        $this->assertDatabaseHas('stream_analytics', [
            'room_id' => $room->id,
            'peak_viewers' => 42,
            'duration_seconds' => 600,
        ]);

        $this->assertDatabaseHas('stream_replays', [
            'room_id' => $room->id,
            'title' => 'Big show',
            'status' => MediaStatus::READY,
            'duration_seconds' => 600,
        ]);

        Sanctum::actingAs($host);
        $this->getJson('/api/v1/creator/analytics/summary')
            ->assertOk()
            ->assertJsonPath('data.streams', 1);

        $replayId = StreamReplay::query()->where('room_id', $room->id)->value('id');
        $this->getJson('/api/v1/creator/replays/'.$replayId)
            ->assertOk()
            ->assertJsonPath('data.views_count', 1);
    }

    public function test_non_host_cannot_create_clip(): void
    {
        $host = User::factory()->create();
        $other = User::factory()->create();
        $room = LiveRoom::factory()->create(['host_id' => $host->id, 'status' => 'live']);

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/creator/rooms/{$room->id}/clips", [
            'title' => 'Nope',
            'start_seconds' => 0,
            'end_seconds' => 10,
        ])->assertForbidden();
    }

    public function test_schedule_rejects_past_times(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/creator/schedules', [
            'title' => 'Too late',
            'scheduled_at' => now()->subHour()->toISOString(),
        ])->assertStatus(422);
    }
}
