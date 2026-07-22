<?php

namespace Tests\Feature;

use App\Features\LiveChat\Events\MessageDeleted;
use App\Features\LiveChat\Events\MessageSent;
use App\Features\LiveChat\Events\ViewerJoined;
use App\Features\LiveChat\Events\ViewerLeft;
use App\Features\LiveChat\Models\LiveChatModerator;
use App\Features\LiveChat\Models\LiveChatSetting;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveSession;
use App\Features\LiveStreaming\Models\LiveViewer;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveChatTest extends TestCase
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

    public function test_user_can_send_message(): void
    {
        Event::fake([MessageSent::class]);
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $viewer);

        Sanctum::actingAs($viewer);
        $response = $this->postJson("/api/v1/live/{$room->id}/messages", [
            'message' => 'Hello live chat',
            'metadata' => ['client' => 'ios'],
        ])->assertCreated()
            ->assertJsonPath('data.message', 'Hello live chat')
            ->assertJsonPath('data.type', LiveMessage::TYPE_TEXT)
            ->assertJsonPath('data.room_id', $room->id)
            ->assertJsonPath('data.user.id', $viewer->id)
            ->assertJsonPath('data.metadata.client', 'ios');

        $this->assertDatabaseHas('live_messages', [
            'id' => $response->json('data.id'),
            'room_id' => $room->id,
            'user_id' => $viewer->id,
            'message' => 'Hello live chat',
            'type' => LiveMessage::TYPE_TEXT,
        ]);
        $this->assertDatabaseHas('live_chat_settings', ['room_id' => $room->id]);
        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($room, $viewer): bool {
            return $event->message->room_id === $room->id
                && $event->message->user_id === $viewer->id
                && $event->broadcastAs() === 'message.sent'
                && $event->broadcastOn()[0]->name === 'private-live.room.'.$room->id;
        });
    }

    public function test_message_sent_is_broadcast_on_live_room_channel(): void
    {
        Event::fake([MessageSent::class]);
        $host = User::factory()->create();
        $room = $this->liveRoom($host);
        Sanctum::actingAs($host);

        $this->postJson("/api/v1/live/{$room->id}/messages", ['message' => 'Host says hi'])
            ->assertCreated();

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($room): bool {
            $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

            return in_array('private-live.room.'.$room->id, $channels, true)
                && $event->broadcastAs() === 'message.sent'
                && $event->broadcastWith()['message']['message'] === 'Host says hi';
        });
    }

    public function test_host_can_delete_message_and_broadcasts_deleted_event(): void
    {
        Event::fake([MessageDeleted::class]);
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $viewer);
        $message = LiveMessage::factory()->create([
            'room_id' => $room->id,
            'user_id' => $viewer->id,
            'message' => 'Remove me',
        ]);

        Sanctum::actingAs($viewer);
        $this->deleteJson("/api/v1/live/{$room->id}/messages/{$message->id}")->assertForbidden();

        Sanctum::actingAs($host);
        $this->deleteJson("/api/v1/live/{$room->id}/messages/{$message->id}")->assertNoContent();
        $this->assertSoftDeleted('live_messages', ['id' => $message->id]);

        Event::assertDispatched(MessageDeleted::class, function (MessageDeleted $event) use ($room, $message): bool {
            return $event->roomId === $room->id
                && $event->messageId === $message->id
                && $event->broadcastAs() === 'message.deleted'
                && $event->broadcastOn()[0]->name === 'private-live.room.'.$room->id;
        });
    }

    public function test_moderator_can_delete_messages(): void
    {
        Event::fake([MessageDeleted::class]);
        $host = User::factory()->create();
        $moderator = User::factory()->create();
        $viewer = User::factory()->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $viewer);
        LiveChatModerator::factory()->create([
            'room_id' => $room->id,
            'user_id' => $moderator->id,
            'role' => LiveChatModerator::ROLE_MODERATOR,
        ]);

        $message = LiveMessage::factory()->create([
            'room_id' => $room->id,
            'user_id' => $viewer->id,
            'message' => 'Spam',
        ]);

        Sanctum::actingAs($moderator);
        $this->deleteJson("/api/v1/live/{$room->id}/messages/{$message->id}")->assertNoContent();
        $this->assertSoftDeleted('live_messages', ['id' => $message->id]);
        Event::assertDispatched(MessageDeleted::class);
    }

    public function test_spam_protection_enforces_slow_mode_duplicates_links_and_rate_limits(): void
    {
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $viewer);
        LiveChatSetting::query()->where('room_id', $room->id)->update([
            'slow_mode_seconds' => 30,
            'followers_only' => false,
            'allow_links' => false,
        ]);

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/live/{$room->id}/messages", [
            'message' => 'visit https://spam.example.com',
        ])->assertUnprocessable();

        LiveChatSetting::query()->where('room_id', $room->id)->update(['allow_links' => true, 'slow_mode_seconds' => 30]);

        $this->postJson("/api/v1/live/{$room->id}/messages", ['message' => 'First message'])->assertCreated();
        $this->postJson("/api/v1/live/{$room->id}/messages", ['message' => 'First message'])->assertUnprocessable();
        $this->postJson("/api/v1/live/{$room->id}/messages", ['message' => 'Second message'])->assertUnprocessable();

        LiveChatSetting::query()->where('room_id', $room->id)->update(['slow_mode_seconds' => 0]);
        RateLimiter::clear(sprintf('live-chat-send:%s:%s', $room->id, $viewer->id));

        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/api/v1/live/{$room->id}/messages", ['message' => "Burst {$i}"])->assertCreated();
        }
        $this->postJson("/api/v1/live/{$room->id}/messages", ['message' => 'Burst overflow'])->assertUnprocessable();
    }

    public function test_join_and_leave_create_typed_messages_and_broadcast_viewer_events(): void
    {
        Event::fake([ViewerJoined::class, ViewerLeft::class, MessageSent::class]);
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $room = $this->liveRoom($host);

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/live/{$room->id}/join")->assertOk();

        Event::assertDispatched(ViewerJoined::class, function (ViewerJoined $event) use ($room, $viewer): bool {
            return $event->roomId === $room->id
                && $event->viewerId === $viewer->id
                && $event->broadcastOn()[0]->name === 'private-live.room.'.$room->id;
        });
        $this->assertDatabaseHas('live_messages', [
            'room_id' => $room->id,
            'user_id' => $viewer->id,
            'type' => LiveMessage::TYPE_JOIN,
        ]);

        $this->postJson("/api/v1/live/{$room->id}/leave")->assertOk();
        Event::assertDispatched(ViewerLeft::class, function (ViewerLeft $event) use ($room, $viewer): bool {
            return $event->roomId === $room->id
                && $event->viewerId === $viewer->id
                && $event->broadcastOn()[0]->name === 'private-live.room.'.$room->id;
        });
        $this->assertDatabaseHas('live_messages', [
            'room_id' => $room->id,
            'user_id' => $viewer->id,
            'type' => LiveMessage::TYPE_LEAVE,
        ]);
    }

    public function test_messages_list_is_paginated_and_requires_participant(): void
    {
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $viewer);
        LiveMessage::factory()->count(2)->create(['room_id' => $room->id, 'user_id' => $viewer->id]);

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/live/{$room->id}/messages?per_page=10")
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/live/{$room->id}/messages")->assertForbidden();
        $this->postJson("/api/v1/live/{$room->id}/messages", ['message' => 'nope'])->assertForbidden();
    }

    private function liveRoom(User $host): LiveRoom
    {
        $room = LiveRoom::factory()->create([
            'host_id' => $host->id,
            'status' => 'live',
            'started_at' => now(),
        ]);
        LiveSession::query()->create(['room_id' => $room->id]);
        LiveChatSetting::factory()->create(['room_id' => $room->id]);

        return $room->fresh();
    }

    private function joinViewer(LiveRoom $room, User $viewer): void
    {
        LiveViewer::factory()->create([
            'room_id' => $room->id,
            'user_id' => $viewer->id,
            'joined_at' => now(),
            'left_at' => null,
        ]);
    }
}
