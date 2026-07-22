<?php

namespace Tests\Feature;

use App\Features\Gifts\Events\GiftSent;
use App\Features\Gifts\Models\Gift;
use App\Features\Gifts\Models\GiftCategory;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveChat\Models\LiveChatSetting;
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

class GiftSystemTest extends TestCase
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

    public function test_gift_list_returns_active_catalog(): void
    {
        $activeCategory = GiftCategory::factory()->create(['name' => 'Popular', 'sort_order' => 1]);
        $inactiveCategory = GiftCategory::factory()->create(['status' => 'inactive']);

        $activeGift = Gift::factory()->create([
            'category_id' => $activeCategory->id,
            'name' => 'Rose',
            'coin_price' => 10,
            'animation_url' => 'https://cdn.example.com/rose.json',
        ]);
        Gift::factory()->create([
            'category_id' => $activeCategory->id,
            'status' => 'inactive',
            'name' => 'Hidden',
        ]);
        Gift::factory()->create([
            'category_id' => $inactiveCategory->id,
            'name' => 'Blocked Category Gift',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/gifts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeGift->id)
            ->assertJsonPath('data.0.name', 'Rose')
            ->assertJsonPath('data.0.coin_price', 10)
            ->assertJsonPath('data.0.animation.url', 'https://cdn.example.com/rose.json')
            ->assertJsonPath('data.0.category.id', $activeCategory->id);
    }

    public function test_viewer_can_send_gift_and_creates_transaction(): void
    {
        Event::fake([GiftSent::class]);

        $host = User::factory()->create();
        $viewer = User::factory()->withCoins(500)->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $viewer);
        $gift = Gift::factory()->create(['coin_price' => 50, 'animation_url' => 'https://cdn.example.com/heart.json']);

        Sanctum::actingAs($viewer);
        $response = $this->postJson("/api/v1/live/{$room->id}/gifts/send", [
            'gift_id' => $gift->id,
            'quantity' => 2,
            'metadata' => ['source' => 'panel'],
        ])->assertCreated()
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.coins', 100)
            ->assertJsonPath('data.sender.id', $viewer->id)
            ->assertJsonPath('data.receiver.id', $host->id)
            ->assertJsonPath('data.gift.id', $gift->id)
            ->assertJsonPath('data.animation.url', 'https://cdn.example.com/heart.json');

        $this->assertDatabaseHas('gift_transactions', [
            'id' => $response->json('data.id'),
            'sender_id' => $viewer->id,
            'receiver_id' => $host->id,
            'room_id' => $room->id,
            'gift_id' => $gift->id,
            'quantity' => 2,
            'coins' => 100,
        ]);

        $this->assertSame(400, $viewer->fresh()->wallet->coins_balance);
        $this->assertSame(100, $host->fresh()->wallet->diamonds_balance);
    }

    public function test_send_gift_validates_insufficient_balance(): void
    {
        $host = User::factory()->create();
        $viewer = User::factory()->withCoins(20)->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $viewer);
        $gift = Gift::factory()->create(['coin_price' => 50]);

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/live/{$room->id}/gifts/send", [
            'gift_id' => $gift->id,
            'quantity' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['coins']);

        $this->assertDatabaseCount('gift_transactions', 0);
        $this->assertSame(20, $viewer->fresh()->wallet->coins_balance);
        $this->assertSame(0, $host->fresh()->wallet->diamonds_balance);
    }

    public function test_gift_sent_is_broadcast_on_live_room_channel(): void
    {
        Event::fake([GiftSent::class]);

        $host = User::factory()->create();
        $viewer = User::factory()->withCoins(200)->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $viewer);
        $gift = Gift::factory()->create([
            'name' => 'Crown',
            'coin_price' => 25,
            'animation_url' => 'https://cdn.example.com/crown.json',
        ]);

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/live/{$room->id}/gifts/send", [
            'gift_id' => $gift->id,
            'quantity' => 3,
        ])->assertCreated();

        Event::assertDispatched(GiftSent::class, function (GiftSent $event) use ($room, $viewer, $host, $gift): bool {
            $payload = $event->broadcastWith();

            return $event->transaction->room_id === $room->id
                && $event->broadcastAs() === 'gift.sent'
                && $event->broadcastOn()[0]->name === 'private-live.room.'.$room->id
                && $payload['sender']['id'] === $viewer->id
                && $payload['receiver']['id'] === $host->id
                && $payload['gift']['id'] === $gift->id
                && $payload['quantity'] === 3
                && $payload['coins'] === 75
                && $payload['animation']['url'] === 'https://cdn.example.com/crown.json';
        });
    }

    public function test_cannot_gift_yourself_and_requires_live_participant(): void
    {
        $host = User::factory()->withCoins(1000)->create();
        $outsider = User::factory()->withCoins(1000)->create();
        $room = $this->liveRoom($host);
        $gift = Gift::factory()->create(['coin_price' => 10]);

        Sanctum::actingAs($host);
        $this->postJson("/api/v1/live/{$room->id}/gifts/send", [
            'gift_id' => $gift->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['gift']);

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/live/{$room->id}/gifts/send", [
            'gift_id' => $gift->id,
        ])->assertForbidden();

        $ended = $this->liveRoom($host, status: 'ended');
        $viewer = User::factory()->withCoins(1000)->create();
        $this->joinViewer($ended, $viewer);

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/live/{$ended->id}/gifts/send", [
            'gift_id' => $gift->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['room']);
    }

    public function test_duplicate_client_request_id_is_idempotent(): void
    {
        Event::fake([GiftSent::class]);

        $host = User::factory()->create();
        $viewer = User::factory()->withCoins(500)->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $viewer);
        $gift = Gift::factory()->create(['coin_price' => 40]);

        Sanctum::actingAs($viewer);
        $first = $this->postJson("/api/v1/live/{$room->id}/gifts/send", [
            'gift_id' => $gift->id,
            'quantity' => 1,
            'client_request_id' => 'req-abc-123',
        ])->assertCreated();

        $second = $this->postJson("/api/v1/live/{$room->id}/gifts/send", [
            'gift_id' => $gift->id,
            'quantity' => 1,
            'client_request_id' => 'req-abc-123',
        ])->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('gift_transactions', 1);
        $this->assertSame(460, $viewer->fresh()->wallet->coins_balance);
        $this->assertSame(40, $host->fresh()->wallet->diamonds_balance);
        Event::assertDispatchedTimes(GiftSent::class, 1);
    }

    public function test_gift_history_permission_checks(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $gift = Gift::factory()->create(['coin_price' => 15]);
        $room = $this->liveRoom($owner);

        GiftTransaction::factory()->create([
            'sender_id' => $other->id,
            'receiver_id' => $owner->id,
            'room_id' => $room->id,
            'gift_id' => $gift->id,
            'quantity' => 1,
            'coins' => 15,
        ]);

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/users/{$owner->id}/gift-history")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/users/{$owner->id}/gift-history")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.coins', 15)
            ->assertJsonPath('data.0.receiver.id', $owner->id);
    }

    public function test_gift_send_rate_limit_is_enforced(): void
    {
        $host = User::factory()->create();
        $viewer = User::factory()->withCoins(10000)->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $viewer);
        $gift = Gift::factory()->create(['coin_price' => 1]);

        Sanctum::actingAs($viewer);
        RateLimiter::clear(sprintf('live-gift-send:%s:%s', $room->id, $viewer->id));

        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/api/v1/live/{$room->id}/gifts/send", [
                'gift_id' => $gift->id,
            ])->assertCreated();
        }

        $this->postJson("/api/v1/live/{$room->id}/gifts/send", [
            'gift_id' => $gift->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['gift']);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $host = User::factory()->create();
        $room = $this->liveRoom($host);
        $gift = Gift::factory()->create();

        $this->getJson('/api/v1/gifts')->assertUnauthorized();
        $this->postJson("/api/v1/live/{$room->id}/gifts/send", ['gift_id' => $gift->id])->assertUnauthorized();
        $this->getJson("/api/v1/users/{$host->id}/gift-history")->assertUnauthorized();
    }

    private function liveRoom(User $host, string $status = 'live'): LiveRoom
    {
        $room = LiveRoom::factory()->create([
            'host_id' => $host->id,
            'status' => $status,
            'started_at' => $status === 'live' ? now() : null,
            'ended_at' => $status === 'ended' ? now() : null,
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
