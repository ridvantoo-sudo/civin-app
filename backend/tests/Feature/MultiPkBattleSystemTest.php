<?php

namespace Tests\Feature;

use App\Features\Gifts\Events\GiftSent;
use App\Features\Gifts\Models\Gift;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\MultiPkBattle\Events\MultiPkAccepted;
use App\Features\MultiPkBattle\Events\MultiPkFinished;
use App\Features\MultiPkBattle\Events\MultiPkInvited;
use App\Features\MultiPkBattle\Events\MultiPkScoreUpdated;
use App\Features\MultiPkBattle\Events\MultiPkStarted;
use App\Features\MultiPkBattle\Jobs\ExpireMultiPkInvitation;
use App\Features\MultiPkBattle\Jobs\FinalizeMultiPkBattle;
use App\Features\MultiPkBattle\Listeners\UpdateMultiPkScoreFromGift;
use App\Features\MultiPkBattle\Models\MultiPkBattle;
use App\Features\MultiPkBattle\Services\MultiPkBattleService;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MultiPkBattleSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_creation_invites_up_to_four_hosts_and_each_invitee_accepts_individually(): void
    {
        Event::fake([MultiPkInvited::class, MultiPkAccepted::class]);
        [$hosts, $rooms] = $this->fourRooms();

        $battle = $this->createBattle($hosts[0], $rooms, MultiPkBattle::MODE_FFA_4);

        $this->assertCount(4, $battle->participants);
        $this->assertNotNull($battle->invitation_expires_at);
        $this->assertTrue($battle->participants->firstWhere('user_id', $hosts[0]->id)->accepted);
        $this->assertSame(1, $battle->participants->where('accepted', true)->count());
        Event::assertDispatched(MultiPkInvited::class, function ($event) use ($rooms): bool {
            $channels = collect($event->broadcastOn())->map->name->all();

            return collect($rooms)->every(
                fn (LiveRoom $room) => in_array('private-live.room.'.$room->id, $channels, true)
            );
        });

        Sanctum::actingAs($hosts[1]);
        $this->postJson("/api/v1/live/{$rooms[1]->id}/multi-pk/{$battle->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.participants.1.accepted', true);
        $this->assertDatabaseHas('multi_pk_participants', [
            'multi_pk_battle_id' => $battle->id,
            'user_id' => $hosts[1]->id,
            'accepted' => true,
        ]);
        Event::assertDispatched(MultiPkAccepted::class);
    }

    public function test_team_battle_starts_only_after_all_four_hosts_accept(): void
    {
        Event::fake([MultiPkStarted::class]);
        Queue::fake();
        [$hosts, $rooms] = $this->fourRooms();
        $battle = $this->createBattle($hosts[0], $rooms, MultiPkBattle::MODE_TEAM_2V2);

        $this->accept($hosts[1], $rooms[1], $battle);
        $this->accept($hosts[2], $rooms[2], $battle);
        Sanctum::actingAs($hosts[0]);
        $this->postJson("/api/v1/multi-pk/{$battle->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('battle');
        $this->accept($hosts[3], $rooms[3], $battle);

        $battle->refresh()->load('participants');
        $this->assertSame(MultiPkBattle::STATUS_RUNNING, $battle->status);
        $this->assertSame(4, $battle->participants->where('active', true)->count());
        $this->assertSame([1, 1, 2, 2], $battle->participants->where('active', true)->pluck('team')->sort()->values()->all());
        $this->assertTrue($battle->started_at->isFuture());
        Queue::assertPushed(FinalizeMultiPkBattle::class);
        Event::assertDispatched(MultiPkStarted::class);
    }

    public function test_team_and_ffa_winners_and_draws_are_calculated_from_active_scores(): void
    {
        Event::fake([MultiPkFinished::class]);
        Queue::fake();
        [$teamHosts, $teamRooms] = $this->fourRooms();
        $team = $this->createBattle($teamHosts[0], $teamRooms, MultiPkBattle::MODE_TEAM_2V2);
        foreach ([1, 2, 3] as $index) {
            $this->accept($teamHosts[$index], $teamRooms[$index], $team);
        }
        $this->assertSame(MultiPkBattle::STATUS_RUNNING, $team->fresh()->status);
        $team->participants()->where('team', 1)->update(['score' => 25]);
        $team->participants()->where('team', 2)->update(['score' => 10]);
        $this->end($teamHosts[1], $team)->assertJsonPath('data.winning_team', 1);

        [$ffaHosts, $ffaRooms] = $this->fourRooms();
        $ffa = $this->createBattle($ffaHosts[0], $ffaRooms, MultiPkBattle::MODE_FFA_4);
        foreach ([1, 2, 3] as $index) {
            $this->accept($ffaHosts[$index], $ffaRooms[$index], $ffa);
        }
        $this->assertSame(MultiPkBattle::STATUS_RUNNING, $ffa->fresh()->status);
        $ffa->participants()->where('user_id', $ffaHosts[2]->id)->update(['score' => 99]);
        $this->end($ffaHosts[3], $ffa)->assertJsonPath('data.winner_id', $ffaHosts[2]->id);

        $this->assertDatabaseCount('pk_rewards', 0);
    }

    public function test_queued_gift_listener_scores_active_receiver_only_after_countdown(): void
    {
        Event::fake([MultiPkScoreUpdated::class]);
        Queue::fake();
        [$hosts, $rooms] = $this->fourRooms();
        $battle = $this->createBattle($hosts[0], $rooms, MultiPkBattle::MODE_FFA_4, countdown: 3);
        foreach ([1, 2, 3] as $index) {
            $this->accept($hosts[$index], $rooms[$index], $battle);
        }
        $battle->refresh();

        $gift = Gift::factory()->create();
        $before = GiftTransaction::query()->create([
            'sender_id' => User::factory()->create()->id,
            'receiver_id' => $hosts[0]->id,
            'room_id' => $rooms[0]->id,
            'gift_id' => $gift->id,
            'quantity' => 1,
            'coins' => 40,
            'created_at' => $battle->started_at->copy()->subSecond(),
        ]);
        $listener = app(UpdateMultiPkScoreFromGift::class);
        $listener->handle(new GiftSent($before));
        $this->assertSame(0, $battle->participants()->where('user_id', $hosts[0]->id)->value('score'));

        $after = $before->replicate()->fill([
            'coins' => 75,
            'created_at' => $battle->started_at,
        ]);
        $after->save();
        $listener->handle(new GiftSent($after));
        $this->assertSame(75, $battle->participants()->where('user_id', $hosts[0]->id)->value('score'));
        Event::assertDispatched(MultiPkScoreUpdated::class);
    }

    public function test_permissions_and_active_legacy_conflicts_are_enforced(): void
    {
        [$hosts, $rooms] = $this->fourRooms();
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/live/{$rooms[0]->id}/multi-pk/request", [
            'mode' => MultiPkBattle::MODE_FFA_4,
            'invited_room_ids' => collect($rooms)->skip(1)->pluck('id')->all(),
        ])->assertForbidden();

        PkBattle::query()->create([
            'room_a_id' => $rooms[1]->id,
            'room_b_id' => $rooms[2]->id,
            'host_a_id' => $hosts[1]->id,
            'host_b_id' => $hosts[2]->id,
            'status' => PkBattle::STATUS_WAITING,
            'duration_seconds' => 60,
            'created_at' => now(),
        ]);
        Sanctum::actingAs($hosts[0]);
        $this->postJson("/api/v1/live/{$rooms[0]->id}/multi-pk/request", [
            'mode' => MultiPkBattle::MODE_FFA_4,
            'invited_room_ids' => collect($rooms)->skip(1)->pluck('id')->all(),
        ])->assertUnprocessable()->assertJsonValidationErrors('rooms');
    }

    public function test_exactly_four_hosts_are_required_and_all_accepting_starts_immediately(): void
    {
        Event::fake([MultiPkStarted::class]);
        Queue::fake();
        [$hosts, $rooms] = $this->fourRooms();

        Sanctum::actingAs($hosts[0]);
        $this->postJson("/api/v1/live/{$rooms[0]->id}/multi-pk/request", [
            'mode' => MultiPkBattle::MODE_TEAM_2V2,
            'invited_room_ids' => [$rooms[1]->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('invited_room_ids');

        $battle = $this->createBattle($hosts[0], $rooms, MultiPkBattle::MODE_TEAM_2V2);
        foreach ([1, 2, 3] as $index) {
            $this->accept($hosts[$index], $rooms[$index], $battle);
        }
        $this->assertSame(MultiPkBattle::STATUS_RUNNING, $battle->fresh()->status);
        $this->assertSame(4, $battle->participants()->where('active', true)->count());
        Event::assertDispatched(MultiPkStarted::class);
    }

    public function test_expiry_cancels_when_not_all_hosts_accept(): void
    {
        Event::fake([MultiPkStarted::class, MultiPkFinished::class]);
        Queue::fake();
        [$hosts, $rooms] = $this->fourRooms();
        $cancelled = $this->createBattle($hosts[0], $rooms, MultiPkBattle::MODE_FFA_4);
        $this->accept($hosts[1], $rooms[1], $cancelled);
        $cancelled->update(['invitation_expires_at' => now()->subSecond()]);
        (new ExpireMultiPkInvitation($cancelled->id))->handle(app(MultiPkBattleService::class));
        $this->assertSame(MultiPkBattle::STATUS_CANCELLED, $cancelled->fresh()->status);
        Event::assertDispatched(MultiPkFinished::class);
    }

    public function test_early_start_is_rejected_and_late_accept_is_rejected(): void
    {
        Queue::fake();
        [$hosts, $rooms] = $this->fourRooms();
        $battle = $this->createBattle($hosts[0], $rooms, MultiPkBattle::MODE_FFA_4);
        $this->accept($hosts[1], $rooms[1], $battle);

        Sanctum::actingAs($hosts[1]);
        $this->postJson("/api/v1/multi-pk/{$battle->id}/start")->assertForbidden();
        Sanctum::actingAs($hosts[0]);
        $this->postJson("/api/v1/multi-pk/{$battle->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('battle');
        $this->accept($hosts[2], $rooms[2], $battle);
        $this->accept($hosts[3], $rooms[3], $battle);

        Sanctum::actingAs($hosts[2]);
        $this->postJson("/api/v1/live/{$rooms[2]->id}/multi-pk/{$battle->id}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('battle');
    }

    public function test_auto_finalizer_service_and_job_are_idempotent(): void
    {
        Queue::fake();
        Event::fake([MultiPkFinished::class]);
        [$hosts, $rooms] = $this->fourRooms();
        $battle = $this->createBattle($hosts[0], $rooms, MultiPkBattle::MODE_FFA_4, countdown: 0);
        foreach ([1, 2, 3] as $index) {
            $this->accept($hosts[$index], $rooms[$index], $battle);
        }
        $battle->refresh();
        $battle->update(['started_at' => now()->subSeconds($battle->duration_seconds + 1)]);

        $job = new FinalizeMultiPkBattle($battle->id);
        $job->handle(app(MultiPkBattleService::class));
        $job->handle(app(MultiPkBattleService::class));

        $this->assertSame(MultiPkBattle::STATUS_FINISHED, $battle->fresh()->status);
        Event::assertDispatchedTimes(MultiPkFinished::class, 1);
    }

    private function createBattle(
        User $initiator,
        array $rooms,
        string $mode,
        int $countdown = 3,
    ): MultiPkBattle {
        Sanctum::actingAs($initiator);
        $id = $this->postJson("/api/v1/live/{$rooms[0]->id}/multi-pk/request", [
            'mode' => $mode,
            'invited_room_ids' => collect($rooms)->skip(1)->pluck('id')->all(),
            'duration_seconds' => 60,
            'countdown_seconds' => $countdown,
        ])->assertCreated()->json('data.id');

        return MultiPkBattle::query()->with('participants')->findOrFail($id);
    }

    private function accept(User $host, LiveRoom $room, MultiPkBattle $battle): void
    {
        Sanctum::actingAs($host);
        $this->postJson("/api/v1/live/{$room->id}/multi-pk/{$battle->id}/accept")->assertOk();
    }

    private function start(User $host, MultiPkBattle $battle): void
    {
        Sanctum::actingAs($host);
        $this->postJson("/api/v1/multi-pk/{$battle->id}/start")->assertOk();
    }

    private function end(User $host, MultiPkBattle $battle)
    {
        Sanctum::actingAs($host);

        return $this->postJson("/api/v1/multi-pk/{$battle->id}/end")->assertOk();
    }

    private function fourRooms(): array
    {
        $hosts = User::factory()->count(4)->create()->all();
        $rooms = collect($hosts)->map(fn (User $host) => LiveRoom::factory()->create([
            'host_id' => $host->id,
            'status' => 'live',
            'started_at' => now(),
        ]))->all();

        return [$hosts, $rooms];
    }
}
