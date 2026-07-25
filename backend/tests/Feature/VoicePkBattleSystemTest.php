<?php

namespace Tests\Feature;

use App\Features\Gifts\Models\Gift;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Users\Models\User;
use App\Features\VoicePkBattle\Events\VoicePkAccepted;
use App\Features\VoicePkBattle\Events\VoicePkFinished;
use App\Features\VoicePkBattle\Events\VoicePkInvited;
use App\Features\VoicePkBattle\Events\VoicePkScoreUpdated;
use App\Features\VoicePkBattle\Events\VoicePkStarted;
use App\Features\VoicePkBattle\Jobs\ExpireVoicePkInvitation;
use App\Features\VoicePkBattle\Jobs\FinalizeVoicePkBattle;
use App\Features\VoicePkBattle\Models\VoicePkBattle;
use App\Features\VoicePkBattle\Services\VoicePkBattleService;
use App\Features\VoiceRoom\Models\VoiceParticipant;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class VoicePkBattleSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosts_invite_accept_and_start_with_server_timestamps_and_room_broadcasts(): void
    {
        Event::fake([VoicePkInvited::class, VoicePkAccepted::class, VoicePkStarted::class]);
        Queue::fake();
        [$hosts, $rooms] = $this->twoRooms();

        $battle = $this->service()->invite($rooms[0], $rooms[1], $hosts[0], 60, 3, 15);

        $this->assertSame(VoicePkBattle::STATUS_WAITING, $battle->status);
        $this->assertCount(2, $battle->teams);
        $this->assertTrue($battle->teams->firstWhere('room_id', $rooms[0]->id)->accepted);
        $this->assertFalse($battle->teams->firstWhere('room_id', $rooms[1]->id)->accepted);
        Event::assertDispatched(VoicePkInvited::class, function (VoicePkInvited $event) use ($rooms): bool {
            $channels = collect($event->broadcastOn())->map->name->sort()->values()->all();
            $expected = collect($rooms)->map(fn (VoiceRoom $room) => 'private-voice.room.'.$room->id)->sort()->values()->all();

            return $channels === $expected;
        });
        Queue::assertPushed(ExpireVoicePkInvitation::class);

        $started = $this->service()->accept($battle, $rooms[1], $hosts[1]);

        $this->assertSame(VoicePkBattle::STATUS_RUNNING, $started->status);
        $this->assertTrue($started->teams->every->accepted);
        $this->assertTrue($started->started_at->equalTo($started->countdown_started_at->copy()->addSeconds(3)));
        $this->assertTrue($started->ends_at->equalTo($started->started_at->copy()->addSeconds(60)));
        Event::assertDispatched(VoicePkAccepted::class);
        Event::assertDispatched(VoicePkStarted::class);
        Queue::assertPushed(FinalizeVoicePkBattle::class);
    }

    public function test_voice_room_gift_scores_once_and_only_inside_the_authoritative_window(): void
    {
        Event::fake([VoicePkScoreUpdated::class]);
        Queue::fake();
        [$hosts, $rooms] = $this->twoRooms();
        $battle = $this->service()->invite($rooms[0], $rooms[1], $hosts[0], 60, 0);
        $battle = $this->service()->accept($battle, $rooms[1], $hosts[1]);

        $gift = GiftTransaction::factory()->create([
            'metadata' => [
                VoicePkBattleService::GIFT_METADATA_TYPE_KEY => VoicePkBattleService::GIFT_METADATA_TYPE,
            ],
            'room_id' => null,
            'voice_room_id' => $rooms[1]->id,
            'coins' => 125,
            'created_at' => $battle->started_at,
        ]);

        $this->service()->applyGift($gift);
        $this->service()->applyGift($gift);

        $team = $battle->teams()->where('room_id', $rooms[1]->id)->firstOrFail();
        $this->assertSame(125, $team->team_score);
        $this->assertSame(125, $team->gift_score);
        $this->assertDatabaseCount('voice_pk_gift_contributions', 1);
        Event::assertDispatchedTimes(VoicePkScoreUpdated::class, 1);

        $beforeCountdown = GiftTransaction::factory()->create([
            'metadata' => [
                VoicePkBattleService::GIFT_METADATA_TYPE_KEY => VoicePkBattleService::GIFT_METADATA_TYPE,
            ],
            'room_id' => null,
            'voice_room_id' => $rooms[0]->id,
            'created_at' => $battle->started_at->copy()->subSecond(),
        ]);
        $this->assertNull($this->service()->applyGift($beforeCountdown));

        $ordinaryGift = GiftTransaction::factory()->create(['metadata' => null]);
        $this->assertNull($this->service()->applyGift($ordinaryGift));
    }

    public function test_due_finalization_selects_winner_and_is_idempotent(): void
    {
        Event::fake([VoicePkFinished::class]);
        Queue::fake();
        [$hosts, $rooms] = $this->twoRooms();
        $battle = $this->service()->invite($rooms[0], $rooms[1], $hosts[0], 15, 0);
        $battle = $this->service()->accept($battle, $rooms[1], $hosts[1]);
        $battle->teams()->where('room_id', $rooms[0]->id)->update(['team_score' => 50]);
        $battle->teams()->where('room_id', $rooms[1]->id)->update(['team_score' => 75]);

        $this->assertSame(VoicePkBattle::STATUS_RUNNING, $this->service()->finalizeIfDue($battle->id)->status);
        $this->travelTo($battle->ends_at->copy()->addSecond());

        $finished = $this->service()->finalizeIfDue($battle->id);
        $again = $this->service()->finalizeIfDue($battle->id);

        $this->assertSame(VoicePkBattle::STATUS_FINISHED, $finished->status);
        $this->assertSame($rooms[1]->id, $finished->winner_room_id);
        $this->assertFalse($finished->is_draw);
        $this->assertSame(VoicePkBattle::STATUS_FINISHED, $again->status);
        Event::assertDispatchedTimes(VoicePkFinished::class, 1);
    }

    public function test_draw_and_unaccepted_invitation_expiry_are_recorded(): void
    {
        Event::fake([VoicePkFinished::class]);
        Queue::fake();
        [$drawHosts, $drawRooms] = $this->twoRooms();
        $draw = $this->service()->invite($drawRooms[0], $drawRooms[1], $drawHosts[0], 15, 0);
        $draw = $this->service()->accept($draw, $drawRooms[1], $drawHosts[1]);
        $this->travelTo($draw->ends_at->copy()->addSecond());
        $draw = $this->service()->finalizeIfDue($draw->id);

        $this->assertTrue($draw->is_draw);
        $this->assertNull($draw->winner_room_id);

        $this->travelBack();
        [$expiryHosts, $expiryRooms] = $this->twoRooms();
        $expired = $this->service()->invite($expiryRooms[0], $expiryRooms[1], $expiryHosts[0], 60, 3, 1);
        $this->travelTo($expired->invitation_expires_at->copy()->addSecond());
        (new ExpireVoicePkInvitation($expired->id))->handle($this->service());

        $this->assertSame(VoicePkBattle::STATUS_CANCELLED, $expired->fresh()->status);
    }

    public function test_authenticated_hosts_can_complete_the_api_lifecycle(): void
    {
        Event::fake();
        Queue::fake();
        [$hosts, $rooms] = $this->twoRooms();

        Sanctum::actingAs($hosts[0]);
        $created = $this->postJson("/api/v1/voice/{$rooms[0]->id}/pk/request", [
            'opponent_room_id' => $rooms[1]->id,
            'duration_seconds' => 60,
            'countdown_seconds' => 3,
        ])->assertCreated()
            ->assertJsonPath('data.room_a.room_id', $rooms[0]->id)
            ->assertJsonPath('data.room_b.room_id', $rooms[1]->id);
        $battleId = $created->json('data.id');

        Sanctum::actingAs($hosts[1]);
        $this->postJson("/api/v1/voice/{$rooms[1]->id}/pk/accept")
            ->assertOk()
            ->assertJsonPath('data.status', VoicePkBattle::STATUS_RUNNING);

        $this->getJson("/api/v1/voice-pk/{$battleId}")
            ->assertOk()
            ->assertJsonPath('data.id', $battleId);
        $this->postJson("/api/v1/voice-pk/{$battleId}/end")
            ->assertOk()
            ->assertJsonPath('data.status', VoicePkBattle::STATUS_FINISHED)
            ->assertJsonPath('data.is_draw', true);
    }

    public function test_voice_room_gift_endpoint_updates_the_active_team_score(): void
    {
        [$hosts, $rooms] = $this->twoRooms();
        $battle = $this->service()->invite($rooms[0], $rooms[1], $hosts[0], 60, 0);
        $battle = $this->service()->accept($battle, $rooms[1], $hosts[1]);
        $sender = User::factory()->withCoins(100)->create();
        VoiceParticipant::factory()->create([
            'room_id' => $rooms[0]->id,
            'user_id' => $sender->id,
            'left_at' => null,
        ]);
        $gift = Gift::factory()->create(['coin_price' => 40]);

        Sanctum::actingAs($sender);
        $this->postJson("/api/v1/voice/{$rooms[0]->id}/gifts/send", [
            'gift_id' => $gift->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.room_id', $rooms[0]->id)
            ->assertJsonPath('data.room_type', 'voice')
            ->assertJsonPath('data.coins', 40);

        $team = $battle->teams()->where('room_id', $rooms[0]->id)->firstOrFail();
        $this->assertSame(40, $team->fresh()->team_score);
        $this->assertSame(40, $team->fresh()->gift_score);
    }

    private function service(): VoicePkBattleService
    {
        return app(VoicePkBattleService::class);
    }

    private function twoRooms(): array
    {
        $hosts = User::factory()->count(2)->create()->all();
        $rooms = collect($hosts)
            ->map(fn (User $host) => VoiceRoom::factory()->create(['host_id' => $host->id]))
            ->all();

        return [$hosts, $rooms];
    }
}
