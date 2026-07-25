<?php

namespace Tests\Feature;

use App\Features\Games\Models\GamePlay;
use App\Features\Games\Plugins\Ludo\Events\LudoMatchFinished;
use App\Features\Games\Plugins\Ludo\Events\LudoRoomCreated;
use App\Features\Games\Plugins\Ludo\Models\LudoConfig;
use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use App\Features\Games\Plugins\Ludo\Models\LudoPlayer;
use App\Features\Games\Plugins\Ludo\Models\LudoTournament;
use App\Features\Games\Plugins\Ludo\Support\LudoRuleEngine;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WalletTransaction;
use Database\Seeders\GamePlatformSeeder;
use Database\Seeders\LudoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LudoPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GamePlatformSeeder::class);
        $this->seed(LudoSeeder::class);
    }

    private function bindDice(int ...$values): void
    {
        $queue = array_values($values);
        $this->app->instance(LudoRuleEngine::class, new LudoRuleEngine(function () use (&$queue): int {
            if ($queue === []) {
                return random_int(1, 6);
            }

            return array_shift($queue);
        }));
    }

    /** @return array{match_id: string, invite_code: string} */
    private function createAndJoin(User $host, User $guest, int $fee = 100): array
    {
        Sanctum::actingAs($host);
        $created = $this->postJson('/api/v1/games/ludo/create', [
            'idempotency_key' => (string) Str::uuid(),
            'mode' => 'classic_2',
            'matchmaking' => 'private',
            'entry_fee_coins' => $fee,
            'max_players' => 2,
        ])->assertCreated();

        $matchId = $created->json('data.match.id');
        $invite = LudoMatch::query()->findOrFail($matchId)->invite_code;

        Sanctum::actingAs($guest);
        $this->postJson('/api/v1/games/ludo/join', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
            'invite_code' => $invite,
        ])->assertCreated();

        return ['match_id' => $matchId, 'invite_code' => $invite];
    }

    private function readyBoth(User $host, User $guest, string $matchId): void
    {
        Sanctum::actingAs($host);
        $this->postJson('/api/v1/games/ludo/ready', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
            'ready' => true,
        ])->assertOk();

        Sanctum::actingAs($guest);
        $this->postJson('/api/v1/games/ludo/ready', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
            'ready' => true,
        ])->assertOk();
    }

    public function test_config_endpoint_exposes_modes_and_rules(): void
    {
        Sanctum::actingAs(User::factory()->withCoins(1000)->create());

        $data = $this->getJson('/api/v1/games/ludo/config')
            ->assertOk()
            ->assertJsonPath('data.slug', 'default')
            ->assertJsonPath('data.enabled', true)
            ->json('data');

        foreach (LudoConfig::MODES as $mode) {
            $this->assertContains($mode, $data['modes_enabled']);
        }
        $this->assertArrayHasKey('rules', $data);
        $this->assertTrue($data['rules']['require_six_to_exit']);
    }

    public function test_guests_and_unauthenticated_callers_cannot_reach_the_plugin(): void
    {
        $this->getJson('/api/v1/games/ludo/config')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['is_guest' => true]));
        $this->getJson('/api/v1/games/ludo/config')->assertForbidden();
    }

    public function test_create_join_ready_start_roll_move_flow(): void
    {
        $this->bindDice(6, 1);
        $host = User::factory()->withCoins(1000)->create();
        $guest = User::factory()->withCoins(1000)->create();

        ['match_id' => $matchId] = $this->createAndJoin($host, $guest);
        $this->assertSame(900, (int) $host->wallet()->first()->coins_balance);
        $this->assertSame(900, (int) $guest->wallet()->first()->coins_balance);
        $this->assertSame(200, (int) LudoMatch::query()->findOrFail($matchId)->pot_coins);

        $this->readyBoth($host, $guest, $matchId);
        $match = LudoMatch::query()->findOrFail($matchId);
        $this->assertSame(LudoMatch::STATUS_ACTIVE, $match->status);
        $this->assertSame('red', $match->current_turn_color);

        Sanctum::actingAs($host);
        $roll = $this->postJson('/api/v1/games/ludo/roll', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
        ])->assertOk();

        $this->assertSame(6, $roll->json('data.dice'));
        $this->assertNotEmpty($roll->json('data.legal_moves'));

        $move = $this->postJson('/api/v1/games/ludo/move', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
            'piece_index' => 0,
        ])->assertOk();

        $this->assertFalse($move->json('data.replay'));
        $this->assertSame('0', (string) LudoMatch::query()->findOrFail($matchId)->board_state['pieces']['red'][0]['position']);
    }

    public function test_create_is_idempotent_and_only_charges_once(): void
    {
        $host = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($host);

        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'mode' => 'classic_2',
            'matchmaking' => 'private',
            'entry_fee_coins' => 100,
            'max_players' => 2,
        ];

        $first = $this->postJson('/api/v1/games/ludo/create', $payload)->assertCreated();
        $second = $this->postJson('/api/v1/games/ludo/create', $payload)
            ->assertOk()
            ->assertJsonPath('data.replay', true);

        $this->assertSame($first->json('data.match.id'), $second->json('data.match.id'));
        $this->assertSame(1, LudoMatch::query()->count());
        $this->assertSame(900, (int) $host->wallet()->first()->coins_balance);
    }

    public function test_create_requires_sufficient_balance(): void
    {
        Sanctum::actingAs(User::factory()->withCoins(10)->create());

        $this->postJson('/api/v1/games/ludo/create', [
            'idempotency_key' => (string) Str::uuid(),
            'mode' => 'classic_2',
            'matchmaking' => 'private',
            'entry_fee_coins' => 100,
            'max_players' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('coins');
    }

    public function test_invalid_turn_and_move_are_rejected(): void
    {
        $this->bindDice(6);
        $host = User::factory()->withCoins(1000)->create();
        $guest = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->createAndJoin($host, $guest);
        $this->readyBoth($host, $guest, $matchId);

        Sanctum::actingAs($guest);
        $this->postJson('/api/v1/games/ludo/roll', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
        ])->assertStatus(422)->assertJsonValidationErrors('turn');

        Sanctum::actingAs($host);
        $this->postJson('/api/v1/games/ludo/move', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
            'piece_index' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('dice');

        $this->postJson('/api/v1/games/ludo/roll', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
        ])->assertOk();

        $this->postJson('/api/v1/games/ludo/move', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
            'piece_index' => 0,
        ])->assertOk();

        // Still red's extra turn after six — force illegal piece move with no dice rolled next
        $this->postJson('/api/v1/games/ludo/move', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
            'piece_index' => 1,
        ])->assertStatus(422);
    }

    public function test_capture_grants_extra_turn(): void
    {
        $this->bindDice(6);
        $host = User::factory()->withCoins(1000)->create();
        $guest = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->createAndJoin($host, $guest);
        $this->readyBoth($host, $guest, $matchId);

        $match = LudoMatch::query()->findOrFail($matchId);
        $board = $match->board_state;
        $board['pieces']['red'][0] = ['index' => 0, 'position' => 1, 'status' => 'board'];
        $board['pieces']['green'][0] = ['index' => 0, 'position' => 4, 'status' => 'board'];
        $match->update(['board_state' => $board, 'dice_value' => 3]);

        $turn = $match->turns()->where('turn_index', $match->turn_index)->first();
        $turn?->update(['dice_value' => 3]);

        Sanctum::actingAs($host);
        $this->postJson('/api/v1/games/ludo/move', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
            'piece_index' => 0,
        ])->assertOk();

        $fresh = LudoMatch::query()->findOrFail($matchId);
        $this->assertSame(-1, $fresh->board_state['pieces']['green'][0]['position']);
        $this->assertSame('red', $fresh->current_turn_color);
    }

    public function test_win_settles_wallet_to_winner(): void
    {
        Event::fake([LudoMatchFinished::class]);
        $host = User::factory()->withCoins(1000)->create();
        $guest = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->createAndJoin($host, $guest, 100);
        $this->readyBoth($host, $guest, $matchId);

        $match = LudoMatch::query()->findOrFail($matchId);
        $board = $match->board_state;
        foreach (range(0, 2) as $i) {
            $board['pieces']['red'][$i] = ['index' => $i, 'position' => 100, 'status' => 'finished'];
        }
        $board['pieces']['red'][3] = ['index' => 3, 'position' => 'home:red:5', 'status' => 'home'];
        $match->update(['board_state' => $board, 'dice_value' => 1]);
        $match->turns()->where('turn_index', $match->turn_index)->update(['dice_value' => 1]);

        Sanctum::actingAs($host);
        $this->postJson('/api/v1/games/ludo/move', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
            'piece_index' => 3,
        ])->assertOk();

        $fresh = LudoMatch::query()->findOrFail($matchId);
        $this->assertSame(LudoMatch::STATUS_FINISHED, $fresh->status);
        $this->assertSame((string) $host->getKey(), (string) $fresh->winner_id);
        $this->assertSame(1100, (int) $host->wallet()->first()->fresh()->coins_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $host->getKey(),
            'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
            'amount' => 200,
            'currency' => WalletTransaction::CURRENCY_COINS,
            'reference_id' => $matchId,
        ]);
        Event::assertDispatched(LudoMatchFinished::class);
    }

    public function test_reconnect_clears_disconnected_flag(): void
    {
        $host = User::factory()->withCoins(1000)->create();
        $guest = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->createAndJoin($host, $guest);
        $this->readyBoth($host, $guest, $matchId);

        LudoPlayer::query()->where('match_id', $matchId)->where('user_id', $host->getKey())
            ->update(['is_connected' => false, 'disconnected_at' => now()]);

        Sanctum::actingAs($host);
        $this->postJson('/api/v1/games/ludo/reconnect', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
        ])->assertOk();

        $player = LudoPlayer::query()->where('match_id', $matchId)->where('user_id', $host->getKey())->firstOrFail();
        $this->assertTrue($player->is_connected);
        $this->assertNull($player->disconnected_at);
        $this->assertSame(1, (int) $player->reconnect_count);
    }

    public function test_leave_before_start_refunds_entry_fee(): void
    {
        $host = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($host);

        $matchId = $this->postJson('/api/v1/games/ludo/create', [
            'idempotency_key' => (string) Str::uuid(),
            'mode' => 'classic_2',
            'matchmaking' => 'private',
            'entry_fee_coins' => 100,
            'max_players' => 2,
        ])->assertCreated()->json('data.match.id');

        $this->assertSame(900, (int) $host->wallet()->first()->coins_balance);

        $this->postJson('/api/v1/games/ludo/leave', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
        ])->assertOk();

        $this->assertSame(1000, (int) $host->wallet()->first()->fresh()->coins_balance);
        $this->assertSame(LudoMatch::STATUS_CANCELLED, LudoMatch::query()->findOrFail($matchId)->status);
    }

    public function test_tournament_join(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        $tournament = LudoTournament::query()->where('slug', 'ludo-daily-cup')->firstOrFail();

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/games/ludo/join-tournament', [
            'idempotency_key' => (string) Str::uuid(),
            'tournament_id' => (string) $tournament->getKey(),
        ])->assertCreated();

        $fresh = $tournament->fresh();
        $this->assertContains((string) $user->getKey(), array_map('strval', $fresh->participant_ids ?? []));
        $this->assertSame(1, (int) $fresh->players_count);
    }

    public function test_create_rejects_client_outcome_fields_and_non_uuid(): void
    {
        Sanctum::actingAs(User::factory()->withCoins(1000)->create());

        $this->postJson('/api/v1/games/ludo/create', [
            'idempotency_key' => 'not-a-uuid',
            'matchmaking' => 'private',
        ])->assertStatus(422)->assertJsonValidationErrors('idempotency_key');

        $this->postJson('/api/v1/games/ludo/create', [
            'idempotency_key' => (string) Str::uuid(),
            'matchmaking' => 'private',
            'winner_id' => (string) Str::uuid(),
            'dice_value' => 6,
            'board_state' => ['pieces' => []],
        ])->assertStatus(422)->assertJsonValidationErrors(['winner_id', 'dice_value', 'board_state']);
    }

    public function test_room_created_event_on_create(): void
    {
        Event::fake([LudoRoomCreated::class]);
        Sanctum::actingAs(User::factory()->withCoins(1000)->create());

        $this->postJson('/api/v1/games/ludo/create', [
            'idempotency_key' => (string) Str::uuid(),
            'mode' => 'classic_2',
            'matchmaking' => 'private',
            'entry_fee_coins' => 0,
            'max_players' => 2,
        ])->assertCreated();

        Event::assertDispatched(LudoRoomCreated::class);
    }
}
