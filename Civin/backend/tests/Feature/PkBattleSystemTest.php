<?php

namespace Tests\Feature;

use App\Features\Gifts\Models\Gift;
use App\Features\LiveChat\Models\LiveChatSetting;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveSession;
use App\Features\LiveStreaming\Models\LiveViewer;
use App\Features\PkBattle\Events\PkFinished;
use App\Features\PkBattle\Events\PkScoreUpdated;
use App\Features\PkBattle\Events\PkStarted;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Models\PkReward;
use App\Features\PkBattle\Models\PkScore;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PkBattleSystemTest extends TestCase
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

    public function test_host_can_request_pk_battle(): void
    {
        [$hostA, $roomA, $hostB, $roomB] = $this->twoLiveRooms();

        Sanctum::actingAs($hostA);
        $response = $this->postJson("/api/v1/live/{$roomA->id}/pk/request", [
            'opponent_room_id' => $roomB->id,
            'duration_seconds' => 120,
        ])->assertCreated()
            ->assertJsonPath('data.status', PkBattle::STATUS_WAITING)
            ->assertJsonPath('data.room_a_id', $roomA->id)
            ->assertJsonPath('data.room_b_id', $roomB->id)
            ->assertJsonPath('data.host_a_id', $hostA->id)
            ->assertJsonPath('data.host_b_id', $hostB->id)
            ->assertJsonPath('data.duration_seconds', 120);

        $this->assertDatabaseHas('pk_battles', [
            'id' => $response->json('data.id'),
            'status' => PkBattle::STATUS_WAITING,
            'room_a_id' => $roomA->id,
            'room_b_id' => $roomB->id,
        ]);
    }

    public function test_host_b_can_accept_pk_battle(): void
    {
        [$hostA, $roomA, $hostB, $roomB] = $this->twoLiveRooms();
        $battle = $this->requestBattle($hostA, $roomA, $roomB);

        Sanctum::actingAs($hostB);
        $this->postJson("/api/v1/live/{$roomB->id}/pk/accept")
            ->assertOk()
            ->assertJsonPath('data.id', $battle->id)
            ->assertJsonPath('data.status', PkBattle::STATUS_WAITING)
            ->assertJsonCount(2, 'data.scores');

        $this->assertDatabaseHas('pk_scores', [
            'pk_battle_id' => $battle->id,
            'user_id' => $hostA->id,
            'score' => 0,
        ]);
        $this->assertDatabaseHas('pk_scores', [
            'pk_battle_id' => $battle->id,
            'user_id' => $hostB->id,
            'score' => 0,
        ]);
    }

    public function test_hosts_can_start_pk_battle_and_broadcasts_started(): void
    {
        Event::fake([PkStarted::class]);

        [$hostA, $roomA, $hostB, $roomB] = $this->twoLiveRooms();
        $battle = $this->acceptedBattle($hostA, $roomA, $hostB, $roomB);

        Sanctum::actingAs($hostA);
        $this->postJson("/api/v1/pk/{$battle->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', PkBattle::STATUS_RUNNING)
            ->assertJsonPath('data.id', $battle->id);

        $this->assertNotNull($battle->fresh()->started_at);

        Event::assertDispatched(PkStarted::class, function (PkStarted $event) use ($battle, $roomA, $roomB): bool {
            $channels = collect($event->broadcastOn())->map->name->all();

            return $event->battle->id === $battle->id
                && $event->broadcastAs() === 'pk.started'
                && in_array('private-live.room.'.$roomA->id, $channels, true)
                && in_array('private-live.room.'.$roomB->id, $channels, true);
        });
    }

    public function test_gift_during_pk_updates_score_and_broadcasts(): void
    {
        Event::fake([PkScoreUpdated::class]);

        [$hostA, $roomA, $hostB, $roomB] = $this->twoLiveRooms();
        $battle = $this->runningBattle($hostA, $roomA, $hostB, $roomB);

        $viewer = User::factory()->withCoins(500)->create();
        $this->joinViewer($roomA, $viewer);
        $gift = Gift::factory()->create(['coin_price' => 40]);

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/live/{$roomA->id}/gifts/send", [
            'gift_id' => $gift->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->assertDatabaseHas('pk_scores', [
            'pk_battle_id' => $battle->id,
            'user_id' => $hostA->id,
            'score' => 80,
            'gift_coins' => 80,
        ]);

        Event::assertDispatched(PkScoreUpdated::class, function (PkScoreUpdated $event) use ($battle, $hostA, $roomA, $roomB): bool {
            $channels = collect($event->broadcastOn())->map->name->all();

            return $event->battle->id === $battle->id
                && $event->score->user_id === $hostA->id
                && $event->score->score === 80
                && $event->broadcastAs() === 'pk.score.updated'
                && in_array('private-live.room.'.$roomA->id, $channels, true)
                && in_array('private-live.room.'.$roomB->id, $channels, true);
        });
    }

    public function test_finish_battle_calculates_winner_creates_reward_and_broadcasts(): void
    {
        Event::fake([PkFinished::class]);

        [$hostA, $roomA, $hostB, $roomB] = $this->twoLiveRooms();
        $battle = $this->runningBattle($hostA, $roomA, $hostB, $roomB);

        PkScore::query()->where('pk_battle_id', $battle->id)->where('user_id', $hostA->id)->update([
            'score' => 250,
            'gift_coins' => 250,
            'updated_at' => now(),
        ]);
        PkScore::query()->where('pk_battle_id', $battle->id)->where('user_id', $hostB->id)->update([
            'score' => 100,
            'gift_coins' => 100,
            'updated_at' => now(),
        ]);

        $hostADiamondsBefore = $hostA->fresh()->wallet->diamonds_balance;

        Sanctum::actingAs($hostB);
        $this->postJson("/api/v1/pk/{$battle->id}/end")
            ->assertOk()
            ->assertJsonPath('data.status', PkBattle::STATUS_FINISHED)
            ->assertJsonPath('data.winner_id', $hostA->id)
            ->assertJsonCount(1, 'data.rewards');

        $this->assertDatabaseHas('pk_battles', [
            'id' => $battle->id,
            'status' => PkBattle::STATUS_FINISHED,
            'winner_id' => $hostA->id,
        ]);
        $this->assertDatabaseHas('pk_rewards', [
            'pk_battle_id' => $battle->id,
            'winner_id' => $hostA->id,
            'reward_type' => PkReward::TYPE_DIAMONDS,
            'amount' => 250,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $hostA->id,
            'type' => WalletTransaction::TYPE_PK_REWARD,
            'amount' => 250,
            'currency' => WalletTransaction::CURRENCY_DIAMONDS,
        ]);
        $this->assertSame($hostADiamondsBefore + 250, $hostA->fresh()->wallet->diamonds_balance);

        Event::assertDispatched(PkFinished::class, function (PkFinished $event) use ($battle, $hostA): bool {
            return $event->battle->id === $battle->id
                && $event->battle->winner_id === $hostA->id
                && $event->broadcastAs() === 'pk.finished';
        });
    }

    public function test_finish_battle_allows_draw_without_reward(): void
    {
        [$hostA, $roomA, $hostB, $roomB] = $this->twoLiveRooms();
        $battle = $this->runningBattle($hostA, $roomA, $hostB, $roomB);

        PkScore::query()->where('pk_battle_id', $battle->id)->update([
            'score' => 150,
            'gift_coins' => 150,
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($hostA);
        $this->postJson("/api/v1/pk/{$battle->id}/end")
            ->assertOk()
            ->assertJsonPath('data.status', PkBattle::STATUS_FINISHED)
            ->assertJsonPath('data.winner_id', null)
            ->assertJsonCount(0, 'data.rewards');

        $this->assertDatabaseCount('pk_rewards', 0);
        $this->assertDatabaseMissing('wallet_transactions', [
            'type' => WalletTransaction::TYPE_PK_REWARD,
        ]);
    }

    public function test_show_pk_battle_returns_payload(): void
    {
        [$hostA, $roomA, $hostB, $roomB] = $this->twoLiveRooms();
        $battle = $this->acceptedBattle($hostA, $roomA, $hostB, $roomB);

        Sanctum::actingAs($hostA);
        $this->getJson("/api/v1/pk/{$battle->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $battle->id)
            ->assertJsonPath('data.host_a.id', $hostA->id)
            ->assertJsonPath('data.host_b.id', $hostB->id)
            ->assertJsonCount(2, 'data.scores');
    }

    public function test_permission_checks_for_pk_actions(): void
    {
        [$hostA, $roomA, $hostB, $roomB] = $this->twoLiveRooms();
        $outsider = User::factory()->create();
        $battle = $this->requestBattle($hostA, $roomA, $roomB);

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/live/{$roomA->id}/pk/request", [
            'opponent_room_id' => $roomB->id,
        ])->assertForbidden();

        Sanctum::actingAs($hostA);
        $this->postJson("/api/v1/live/{$roomB->id}/pk/accept")->assertForbidden();

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/live/{$roomB->id}/pk/accept")->assertForbidden();

        Sanctum::actingAs($hostB);
        $this->postJson("/api/v1/live/{$roomB->id}/pk/accept")->assertOk();

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/pk/{$battle->id}/start")->assertForbidden();
        $this->postJson("/api/v1/pk/{$battle->id}/end")->assertForbidden();

        Sanctum::actingAs($hostA);
        $this->postJson("/api/v1/pk/{$battle->id}/start")->assertOk();

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/pk/{$battle->id}/end")->assertForbidden();
    }

    public function test_cannot_start_before_accept_or_duplicate_active_pk(): void
    {
        [$hostA, $roomA, $hostB, $roomB] = $this->twoLiveRooms();
        $battle = $this->requestBattle($hostA, $roomA, $roomB);

        Sanctum::actingAs($hostA);
        $this->postJson("/api/v1/pk/{$battle->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['battle']);

        Sanctum::actingAs($hostA);
        $this->postJson("/api/v1/live/{$roomA->id}/pk/request", [
            'opponent_room_id' => $roomB->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['room']);

        $otherHost = User::factory()->create();
        $otherRoom = $this->liveRoom($otherHost);

        Sanctum::actingAs($hostB);
        $this->postJson("/api/v1/live/{$roomB->id}/pk/request", [
            'opponent_room_id' => $otherRoom->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['room']);
    }

    public function test_requires_active_live_rooms_and_authentication(): void
    {
        $hostA = User::factory()->create();
        $hostB = User::factory()->create();
        $roomA = $this->liveRoom($hostA, status: 'ended');
        $roomB = $this->liveRoom($hostB);

        Sanctum::actingAs($hostA);
        $this->postJson("/api/v1/live/{$roomA->id}/pk/request", [
            'opponent_room_id' => $roomB->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['room']);

        $liveA = $this->liveRoom($hostA);
        $endedB = $this->liveRoom($hostB, status: 'ended');

        Sanctum::actingAs($hostA);
        $this->postJson("/api/v1/live/{$liveA->id}/pk/request", [
            'opponent_room_id' => $endedB->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['opponent_room_id']);

        $this->app['auth']->forgetGuards();

        $this->postJson("/api/v1/live/{$liveA->id}/pk/request", [
            'opponent_room_id' => $roomB->id,
        ])->assertUnauthorized();
    }

    /**
     * @return array{0: User, 1: LiveRoom, 2: User, 3: LiveRoom}
     */
    private function twoLiveRooms(): array
    {
        $hostA = User::factory()->create();
        $hostB = User::factory()->create();

        return [$hostA, $this->liveRoom($hostA), $hostB, $this->liveRoom($hostB)];
    }

    private function requestBattle(User $hostA, LiveRoom $roomA, LiveRoom $roomB): PkBattle
    {
        Sanctum::actingAs($hostA);
        $id = $this->postJson("/api/v1/live/{$roomA->id}/pk/request", [
            'opponent_room_id' => $roomB->id,
            'duration_seconds' => 180,
        ])->assertCreated()->json('data.id');

        return PkBattle::query()->findOrFail($id);
    }

    private function acceptedBattle(User $hostA, LiveRoom $roomA, User $hostB, LiveRoom $roomB): PkBattle
    {
        $battle = $this->requestBattle($hostA, $roomA, $roomB);

        Sanctum::actingAs($hostB);
        $this->postJson("/api/v1/live/{$roomB->id}/pk/accept")->assertOk();

        return $battle->fresh(['scores']);
    }

    private function runningBattle(User $hostA, LiveRoom $roomA, User $hostB, LiveRoom $roomB): PkBattle
    {
        $battle = $this->acceptedBattle($hostA, $roomA, $hostB, $roomB);

        Sanctum::actingAs($hostA);
        $this->postJson("/api/v1/pk/{$battle->id}/start")->assertOk();

        return $battle->fresh(['scores']);
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
