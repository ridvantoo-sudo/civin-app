<?php

namespace Tests\Feature;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Events\SeatUpdated;
use App\Features\VoiceRoom\Events\SpeakerJoined;
use App\Features\VoiceRoom\Events\SpeakerRemoved;
use App\Features\VoiceRoom\Events\VoiceRoomEnded;
use App\Features\VoiceRoom\Events\VoiceRoomStarted;
use App\Features\VoiceRoom\Models\VoiceParticipant;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Models\VoiceSeat;
use App\Features\VoiceRoom\Models\VoiceSession;
use App\Features\VoiceRoom\Services\VoiceAgoraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VoiceRoomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('agora.app_id', str_repeat('a', 32));
        config()->set('agora.app_certificate', str_repeat('b', 32));
        config()->set('agora.token_ttl', 3600);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_all_voice_endpoints_require_authentication(): void
    {
        $room = VoiceRoom::factory()->create();

        $this->postJson('/api/v1/voice/create')->assertUnauthorized();
        $this->postJson("/api/v1/voice/{$room->id}/join")->assertUnauthorized();
        $this->postJson("/api/v1/voice/{$room->id}/leave")->assertUnauthorized();
        $this->postJson("/api/v1/voice/{$room->id}/seat/request")->assertUnauthorized();
        $this->postJson("/api/v1/voice/{$room->id}/seat/approve")->assertUnauthorized();
        $this->postJson("/api/v1/voice/{$room->id}/seat/reject")->assertUnauthorized();
        $this->postJson("/api/v1/voice/{$room->id}/seat/remove")->assertUnauthorized();
        $this->postJson("/api/v1/voice/{$room->id}/seat/mute")->assertUnauthorized();
        $this->postJson("/api/v1/voice/{$room->id}/end")->assertUnauthorized();
    }

    public function test_host_can_create_voice_room_with_host_token_and_seats(): void
    {
        Event::fake([VoiceRoomStarted::class]);
        $host = User::factory()->create();
        Sanctum::actingAs($host);

        $response = $this->postJson('/api/v1/voice/create', [
            'title' => 'Late night talk',
            'description' => 'Open mic',
            'thumbnail' => 'https://cdn.example.com/voice.jpg',
            'seat_count' => 6,
        ])->assertCreated()
            ->assertJsonPath('data.room.status', VoiceRoom::STATUS_LIVE)
            ->assertJsonPath('data.room.seat_count', 6)
            ->assertJsonPath('data.room.participant_count', 1)
            ->assertJsonPath('data.rtc.app_id', str_repeat('a', 32))
            ->assertJson(fn ($json) => $json->whereType('data.rtc.token', 'string')
                ->whereType('data.rtc.expires_at', 'string')
                ->etc());

        $roomId = $response->json('data.room.id');
        $this->assertSame('007', substr((string) $response->json('data.rtc.token'), 0, 3));
        $this->assertDatabaseCount('voice_seats', 6);
        $this->assertDatabaseHas('voice_seats', [
            'room_id' => $roomId,
            'seat_index' => 0,
            'user_id' => $host->id,
            'status' => VoiceSeat::STATUS_OCCUPIED,
        ]);
        $this->assertDatabaseHas('voice_participants', [
            'room_id' => $roomId,
            'user_id' => $host->id,
            'role' => VoiceParticipant::ROLE_HOST,
        ]);
        $this->assertDatabaseHas('voice_sessions', [
            'room_id' => $roomId,
            'peak_participants' => 1,
        ]);
        Event::assertDispatched(VoiceRoomStarted::class, function (VoiceRoomStarted $event) use ($roomId, $host): bool {
            return $event->roomId === $roomId
                && $event->hostId === $host->id
                && $event->broadcastAs() === 'voice.room.started'
                && $event->broadcastOn()[0]->name === "private-voice.room.{$roomId}";
        });
    }

    public function test_join_and_leave_are_idempotent_and_track_peak(): void
    {
        $host = User::factory()->create();
        $listener = User::factory()->create();
        $room = $this->voiceRoom($host);
        Sanctum::actingAs($listener);

        $first = $this->postJson("/api/v1/voice/{$room->id}/join")
            ->assertOk()
            ->assertJsonPath('data.room.participant_count', 2)
            ->assertJsonPath('data.rtc.app_id', str_repeat('a', 32));
        $audienceUid = $first->json('data.rtc.uid');

        $this->postJson("/api/v1/voice/{$room->id}/join")
            ->assertOk()
            ->assertJsonPath('data.room.participant_count', 2)
            ->assertJsonPath('data.rtc.uid', $audienceUid);

        $this->assertDatabaseCount('voice_participants', 2);
        $this->assertSame(2, $room->session->fresh()->peak_participants);

        $this->postJson("/api/v1/voice/{$room->id}/leave")
            ->assertOk()
            ->assertJsonPath('data.participant_count', 1);
        $this->postJson("/api/v1/voice/{$room->id}/leave")
            ->assertOk()
            ->assertJsonPath('data.participant_count', 1);

        $this->assertNotNull(
            VoiceParticipant::query()->where('room_id', $room->id)->where('user_id', $listener->id)->firstOrFail()->left_at
        );
    }

    public function test_host_cannot_leave_and_ended_room_cannot_be_joined(): void
    {
        $host = User::factory()->create();
        $listener = User::factory()->create();
        $room = $this->voiceRoom($host);

        Sanctum::actingAs($host);
        $this->postJson("/api/v1/voice/{$room->id}/leave")->assertUnprocessable();

        $room->update(['status' => VoiceRoom::STATUS_ENDED, 'ended_at' => now()]);
        Sanctum::actingAs($listener);
        $this->postJson("/api/v1/voice/{$room->id}/join")->assertUnprocessable();
    }

    public function test_seat_request_approve_and_speaker_token_upgrade(): void
    {
        Event::fake([SeatUpdated::class, SpeakerJoined::class]);

        $host = User::factory()->create();
        $speaker = User::factory()->create();
        $room = $this->voiceRoom($host);

        Sanctum::actingAs($speaker);
        $this->postJson("/api/v1/voice/{$room->id}/join")->assertOk();
        $this->postJson("/api/v1/voice/{$room->id}/seat/request", ['seat_index' => 1])
            ->assertOk()
            ->assertJsonPath('data.seats.1.status', VoiceSeat::STATUS_PENDING)
            ->assertJsonPath('data.seats.1.user.id', $speaker->id);

        Sanctum::actingAs($host);
        $this->postJson("/api/v1/voice/{$room->id}/seat/approve", ['seat_index' => 1])
            ->assertOk()
            ->assertJsonPath('data.seats.1.status', VoiceSeat::STATUS_OCCUPIED)
            ->assertJsonPath('data.seats.1.user.id', $speaker->id);

        Sanctum::actingAs($speaker);
        $join = $this->postJson("/api/v1/voice/{$room->id}/join")
            ->assertOk();

        $seat = VoiceSeat::query()->where('room_id', $room->id)->where('seat_index', 1)->firstOrFail();
        $this->assertSame($seat->stream_uid, $join->json('data.rtc.uid'));
        $this->assertSame('007', substr((string) $join->json('data.rtc.token'), 0, 3));
        $this->assertDatabaseHas('voice_participants', [
            'room_id' => $room->id,
            'user_id' => $speaker->id,
            'role' => VoiceParticipant::ROLE_SPEAKER,
        ]);

        Event::assertDispatched(SeatUpdated::class);
        Event::assertDispatched(SpeakerJoined::class, function (SpeakerJoined $event) use ($room, $speaker): bool {
            return $event->roomId === $room->id
                && $event->userId === $speaker->id
                && $event->seatIndex === 1
                && $event->broadcastAs() === 'speaker.joined';
        });
    }

    public function test_seat_reject_clears_pending_request(): void
    {
        Event::fake([SeatUpdated::class]);

        $host = User::factory()->create();
        $user = User::factory()->create();
        $room = $this->voiceRoom($host);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/voice/{$room->id}/join")->assertOk();
        $this->postJson("/api/v1/voice/{$room->id}/seat/request", ['seat_index' => 2])->assertOk();

        Sanctum::actingAs($host);
        $this->postJson("/api/v1/voice/{$room->id}/seat/reject", ['seat_index' => 2])
            ->assertOk()
            ->assertJsonPath('data.seats.2.status', VoiceSeat::STATUS_EMPTY)
            ->assertJsonPath('data.seats.2.user', null);

        Event::assertDispatched(SeatUpdated::class, function (SeatUpdated $event) use ($room): bool {
            return $event->roomId === $room->id
                && $event->seatIndex === 2
                && $event->status === VoiceSeat::STATUS_EMPTY
                && $event->broadcastAs() === 'seat.updated';
        });
    }

    public function test_host_permissions_for_seat_management(): void
    {
        $host = User::factory()->create();
        $other = User::factory()->create();
        $speaker = User::factory()->create();
        $room = $this->voiceRoom($host);

        Sanctum::actingAs($speaker);
        $this->postJson("/api/v1/voice/{$room->id}/join")->assertOk();
        $this->postJson("/api/v1/voice/{$room->id}/seat/request", ['seat_index' => 1])->assertOk();

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/voice/{$room->id}/seat/approve", ['seat_index' => 1])->assertForbidden();
        $this->postJson("/api/v1/voice/{$room->id}/seat/reject", ['seat_index' => 1])->assertForbidden();
        $this->postJson("/api/v1/voice/{$room->id}/seat/remove", ['seat_index' => 1])->assertForbidden();
        $this->postJson("/api/v1/voice/{$room->id}/seat/mute", ['seat_index' => 1])->assertForbidden();
        $this->postJson("/api/v1/voice/{$room->id}/end")->assertForbidden();
    }

    public function test_remove_and_mute_speaker_management(): void
    {
        Event::fake([SeatUpdated::class, SpeakerRemoved::class]);

        $host = User::factory()->create();
        $speaker = User::factory()->create();
        $room = $this->voiceRoom($host);

        Sanctum::actingAs($speaker);
        $this->postJson("/api/v1/voice/{$room->id}/join")->assertOk();
        $this->postJson("/api/v1/voice/{$room->id}/seat/request", ['seat_index' => 3])->assertOk();

        Sanctum::actingAs($host);
        $this->postJson("/api/v1/voice/{$room->id}/seat/approve", ['seat_index' => 3])->assertOk();
        $this->postJson("/api/v1/voice/{$room->id}/seat/mute", ['seat_index' => 3])
            ->assertOk()
            ->assertJsonPath('data.seats.3.is_muted', true);
        $this->postJson("/api/v1/voice/{$room->id}/seat/mute", ['seat_index' => 3, 'muted' => false])
            ->assertOk()
            ->assertJsonPath('data.seats.3.is_muted', false);
        $this->postJson("/api/v1/voice/{$room->id}/seat/remove", ['seat_index' => 3])
            ->assertOk()
            ->assertJsonPath('data.seats.3.status', VoiceSeat::STATUS_EMPTY);

        $this->assertDatabaseHas('voice_participants', [
            'room_id' => $room->id,
            'user_id' => $speaker->id,
            'role' => VoiceParticipant::ROLE_AUDIENCE,
        ]);

        Event::assertDispatched(SpeakerRemoved::class, function (SpeakerRemoved $event) use ($room, $speaker): bool {
            return $event->roomId === $room->id
                && $event->userId === $speaker->id
                && $event->seatIndex === 3
                && $event->broadcastAs() === 'speaker.removed';
        });
    }

    public function test_seat_request_validation_rules(): void
    {
        $host = User::factory()->create();
        $user = User::factory()->create();
        $room = $this->voiceRoom($host);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/voice/{$room->id}/seat/request", ['seat_index' => 1])->assertUnprocessable();

        $this->postJson("/api/v1/voice/{$room->id}/join")->assertOk();
        $this->postJson("/api/v1/voice/{$room->id}/seat/request", ['seat_index' => 0])->assertUnprocessable();
        $this->postJson("/api/v1/voice/{$room->id}/seat/request", ['seat_index' => 99])->assertUnprocessable();

        Sanctum::actingAs($host);
        $this->postJson("/api/v1/voice/{$room->id}/seat/request", ['seat_index' => 1])->assertUnprocessable();
    }

    public function test_end_closes_room_participants_and_broadcasts(): void
    {
        Event::fake([VoiceRoomEnded::class]);
        Carbon::setTestNow('2026-07-22 12:00:00');

        $host = User::factory()->create();
        $listener = User::factory()->create();
        $room = $this->voiceRoom($host, now()->subMinutes(3));

        Sanctum::actingAs($listener);
        $this->postJson("/api/v1/voice/{$room->id}/join")->assertOk();

        Carbon::setTestNow('2026-07-22 12:03:10');
        Sanctum::actingAs($host);
        $this->postJson("/api/v1/voice/{$room->id}/end")
            ->assertOk()
            ->assertJsonPath('data.status', VoiceRoom::STATUS_ENDED)
            ->assertJsonPath('data.participant_count', 0);

        $session = $room->session->fresh();
        $this->assertSame(370, $session->duration);
        $this->assertSame(2, $session->peak_participants);
        $this->assertTrue(
            VoiceParticipant::query()->where('room_id', $room->id)->whereNull('left_at')->doesntExist()
        );
        Event::assertDispatched(VoiceRoomEnded::class, function (VoiceRoomEnded $event) use ($room, $host): bool {
            return $event->roomId === $room->id
                && $event->hostId === $host->id
                && $event->durationSeconds === 370
                && $event->broadcastAs() === 'voice.room.ended';
        });
        $this->postJson("/api/v1/voice/{$room->id}/end")->assertUnprocessable();
        Carbon::setTestNow();
    }

    public function test_voice_agora_service_generates_host_audience_and_speaker_tokens(): void
    {
        $agora = app(VoiceAgoraService::class);
        $channel = $agora->createChannel('room-id');

        $host = $agora->generateHostToken($channel, 42);
        $speaker = $agora->generateSpeakerToken($channel, 99);
        $audience = $agora->generateAudienceToken($channel, 'user-uuid');
        $audienceAgain = $agora->generateAudienceToken($channel, 'user-uuid');

        $this->assertStringStartsWith('007', $host->token);
        $this->assertStringStartsWith('007', $speaker->token);
        $this->assertStringStartsWith('007', $audience->token);
        $this->assertSame(42, $host->uid);
        $this->assertSame(99, $speaker->uid);
        $this->assertSame($audience->uid, $audienceAgain->uid);
        $this->assertGreaterThan(0, $audience->uid);
        $this->assertTrue($agora->validateChannel($channel));
        $this->assertFalse($agora->validateChannel(str_repeat('a', 65)));

        config()->set('agora.app_certificate', '');
        $this->expectException(\RuntimeException::class);
        $agora->generateHostToken($channel, 42);
    }

    public function test_host_cannot_create_second_live_voice_room(): void
    {
        $host = User::factory()->create();
        $this->voiceRoom($host);
        Sanctum::actingAs($host);

        $this->postJson('/api/v1/voice/create', [
            'title' => 'Second room',
        ])->assertUnprocessable();
    }

    private function voiceRoom(User $host, ?Carbon $startedAt = null): VoiceRoom
    {
        $startedAt ??= now();
        $seatCount = VoiceRoom::DEFAULT_SEAT_COUNT;

        $room = VoiceRoom::factory()->create([
            'host_id' => $host->id,
            'status' => VoiceRoom::STATUS_LIVE,
            'seat_count' => $seatCount,
            'participant_count' => 1,
            'started_at' => $startedAt,
        ]);

        for ($index = 0; $index < $seatCount; $index++) {
            VoiceSeat::factory()->create([
                'room_id' => $room->id,
                'seat_index' => $index,
                'user_id' => $index === 0 ? $host->id : null,
                'status' => $index === 0 ? VoiceSeat::STATUS_OCCUPIED : VoiceSeat::STATUS_EMPTY,
                'stream_uid' => $index === 0 ? $room->host_uid : null,
            ]);
        }

        VoiceParticipant::factory()->host()->create([
            'room_id' => $room->id,
            'user_id' => $host->id,
            'joined_at' => $startedAt,
        ]);

        VoiceSession::query()->create([
            'room_id' => $room->id,
            'peak_participants' => 1,
        ]);

        return $room->fresh()->load('session', 'seats', 'host.profile');
    }
}
