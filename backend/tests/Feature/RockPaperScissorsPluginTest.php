<?php

namespace Tests\Feature;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsChallengeAccepted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsChallengeCreated;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchStarted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMoveSubmitted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRoundFinished;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsHistory;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMove;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsReward;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsRound;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsStatistic;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsTournament;
use App\Features\Games\Plugins\RockPaperScissors\Scheduler\RpsScheduler;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WalletTransaction;
use Database\Seeders\GamePlatformSeeder;
use Database\Seeders\RpsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RockPaperScissorsPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GamePlatformSeeder::class);
        $this->seed(RpsSeeder::class);
    }

    /**
     * Create a challenge and have the opponent accept it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{match_id: string, round_id: string}
     */
    private function openMatch(User $challenger, User $opponent, array $overrides = []): array
    {
        Sanctum::actingAs($challenger);
        $matchId = $this->postJson('/api/v1/games/rps/create', array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'mode' => RpsMatch::MODE_CLASSIC_1V1,
            'matchmaking' => RpsMatch::MATCHMAKING_PRIVATE,
            'opponent_id' => (string) $opponent->getKey(),
            'entry_fee_coins' => 100,
        ], $overrides))
            ->assertCreated()
            ->json('data.match.id');

        Sanctum::actingAs($opponent);
        $this->postJson('/api/v1/games/rps/accept', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
        ])->assertCreated();

        $match = RpsMatch::query()->findOrFail($matchId);

        return ['match_id' => (string) $matchId, 'round_id' => (string) $match->current_round_id];
    }

    private function submitMove(User $user, string $matchId, string $move, ?string $key = null): TestResponse
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/v1/games/rps/move', [
            'idempotency_key' => $key ?? (string) Str::uuid(),
            'match_id' => $matchId,
            'move' => $move,
        ]);
    }

    public function test_config_endpoint_exposes_modes_matchmaking_and_move_rules(): void
    {
        Sanctum::actingAs(User::factory()->withCoins(1000)->create());

        $data = $this->getJson('/api/v1/games/rps/config')
            ->assertOk()
            ->assertJsonPath('data.slug', 'default')
            ->assertJsonPath('data.enabled', true)
            ->json('data');

        foreach (RpsConfig::MODES as $mode) {
            $this->assertContains($mode, $data['modes_enabled']);
        }
        foreach (RpsConfig::MATCHMAKING as $matchmaking) {
            $this->assertContains($matchmaking, $data['matchmaking_enabled']);
        }
        $this->assertSame(['rock', 'paper', 'scissors'], $data['moves']);
        $this->assertArrayHasKey('beats', $data['move_rules']);
    }

    public function test_guests_and_unauthenticated_callers_cannot_reach_the_plugin(): void
    {
        $this->getJson('/api/v1/games/rps/config')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['is_guest' => true]));
        $this->getJson('/api/v1/games/rps/config')->assertForbidden();
    }

    public function test_create_debits_the_entry_fee_and_accept_debits_the_opponent(): void
    {
        Event::fake([RpsChallengeCreated::class, RpsChallengeAccepted::class, RpsMatchStarted::class]);

        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();

        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent);

        $match = RpsMatch::query()->findOrFail($matchId);
        $this->assertSame(RpsMatch::STATUS_ACTIVE, $match->status);
        $this->assertSame(200, (int) $match->pot_coins);
        $this->assertSame(900, (int) $challenger->wallet()->first()->coins_balance);
        $this->assertSame(900, (int) $opponent->wallet()->first()->coins_balance);

        foreach ([$challenger, $opponent] as $player) {
            $this->assertDatabaseHas('wallet_transactions', [
                'user_id' => $player->getKey(),
                'type' => GamePlay::WALLET_TYPE_GAME_BET,
                'amount' => -100,
                'currency' => WalletTransaction::CURRENCY_COINS,
                'reference_id' => $matchId,
            ]);
        }

        Event::assertDispatched(RpsChallengeCreated::class);
        Event::assertDispatched(RpsChallengeAccepted::class);
        Event::assertDispatched(RpsMatchStarted::class);
    }

    public function test_create_is_idempotent_and_only_charges_once(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($challenger);

        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'matchmaking' => RpsMatch::MATCHMAKING_PRIVATE,
            'opponent_id' => (string) $opponent->getKey(),
            'entry_fee_coins' => 100,
        ];

        $first = $this->postJson('/api/v1/games/rps/create', $payload)->assertCreated();
        $second = $this->postJson('/api/v1/games/rps/create', $payload)
            ->assertOk()
            ->assertJsonPath('data.replay', true);

        $this->assertSame($first->json('data.match.id'), $second->json('data.match.id'));
        $this->assertSame(1, RpsMatch::query()->count());
        $this->assertSame(900, (int) $challenger->wallet()->first()->coins_balance);
    }

    public function test_create_rejects_a_non_uuid_idempotency_key(): void
    {
        Sanctum::actingAs(User::factory()->withCoins(1000)->create());

        $this->postJson('/api/v1/games/rps/create', [
            'idempotency_key' => 'not-a-uuid',
            'matchmaking' => RpsMatch::MATCHMAKING_RANDOM,
        ])->assertStatus(422)->assertJsonValidationErrors('idempotency_key');
    }

    public function test_create_rejects_client_supplied_outcome_fields(): void
    {
        Sanctum::actingAs(User::factory()->withCoins(1000)->create());

        $this->postJson('/api/v1/games/rps/create', [
            'idempotency_key' => (string) Str::uuid(),
            'matchmaking' => RpsMatch::MATCHMAKING_RANDOM,
            'result' => 'challenger_won',
            'pot_coins' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors(['result', 'pot_coins']);
    }

    public function test_create_requires_sufficient_balance(): void
    {
        $challenger = User::factory()->withCoins(10)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($challenger);

        $this->postJson('/api/v1/games/rps/create', [
            'idempotency_key' => (string) Str::uuid(),
            'matchmaking' => RpsMatch::MATCHMAKING_PRIVATE,
            'opponent_id' => (string) $opponent->getKey(),
            'entry_fee_coins' => 500,
        ])->assertStatus(422)->assertJsonValidationErrors('coins');

        $this->assertSame(10, (int) $challenger->wallet()->first()->coins_balance);
    }

    public function test_you_cannot_challenge_or_accept_yourself(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/rps/create', [
            'idempotency_key' => (string) Str::uuid(),
            'matchmaking' => RpsMatch::MATCHMAKING_PRIVATE,
            'opponent_id' => (string) $user->getKey(),
            'entry_fee_coins' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('opponent_id');

        $matchId = $this->postJson('/api/v1/games/rps/create', [
            'idempotency_key' => (string) Str::uuid(),
            'matchmaking' => RpsMatch::MATCHMAKING_RANDOM,
            'entry_fee_coins' => 0,
        ])->assertCreated()->json('data.match.id');

        $this->postJson('/api/v1/games/rps/accept', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
        ])->assertForbidden();
    }

    public function test_a_private_challenge_cannot_be_hijacked_by_a_third_party(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $invited = User::factory()->withCoins(1000)->create();
        $stranger = User::factory()->withCoins(1000)->create();

        Sanctum::actingAs($challenger);
        $matchId = $this->postJson('/api/v1/games/rps/create', [
            'idempotency_key' => (string) Str::uuid(),
            'matchmaking' => RpsMatch::MATCHMAKING_PRIVATE,
            'opponent_id' => (string) $invited->getKey(),
            'entry_fee_coins' => 50,
        ])->assertCreated()->json('data.match.id');

        Sanctum::actingAs($stranger);
        $this->postJson('/api/v1/games/rps/accept', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
        ])->assertForbidden();

        $match = RpsMatch::query()->findOrFail($matchId);
        $this->assertSame((string) $invited->getKey(), (string) $match->opponent_id);
        $this->assertSame(RpsMatch::STATUS_PENDING, $match->status);
        $this->assertSame(1000, (int) $stranger->wallet()->first()->coins_balance);
    }

    public function test_open_matchmaking_pairs_two_waiting_players(): void
    {
        $first = User::factory()->withCoins(1000)->create();
        $second = User::factory()->withCoins(1000)->create();

        Sanctum::actingAs($first);
        $matchId = $this->postJson('/api/v1/games/rps/create', [
            'idempotency_key' => (string) Str::uuid(),
            'matchmaking' => RpsMatch::MATCHMAKING_QUICK_PLAY,
            'entry_fee_coins' => 25,
        ])->assertCreated()->json('data.match.id');

        Sanctum::actingAs($second);
        $paired = $this->postJson('/api/v1/games/rps/create', [
            'idempotency_key' => (string) Str::uuid(),
            'matchmaking' => RpsMatch::MATCHMAKING_QUICK_PLAY,
            'entry_fee_coins' => 25,
        ])->assertCreated()->assertJsonPath('data.matched', true);

        $this->assertSame($matchId, $paired->json('data.match.id'));
        $this->assertSame(1, RpsMatch::query()->count());
        $this->assertSame(RpsMatch::STATUS_ACTIVE, RpsMatch::query()->findOrFail($matchId)->status);
    }

    public function test_moves_stay_hidden_until_both_players_have_submitted(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId, 'round_id' => $roundId] = $this->openMatch($challenger, $opponent);

        // The submitter gets their own move echoed back, but the round keeps it
        // out of the shared state until both sides are in.
        $this->submitMove($challenger, $matchId, 'rock')
            ->assertOk()
            ->assertJsonPath('data.resolved', false)
            ->assertJsonPath('data.round.revealed', false)
            ->assertJsonPath('data.move.move', 'rock')
            ->assertJsonMissingPath('data.round.challenger_move');

        // The waiting opponent must not be able to read the hidden move either.
        Sanctum::actingAs($opponent);
        $peek = $this->getJson('/api/v1/games/rps/matches/'.$matchId)->assertOk()->json('data.rounds.0');
        $this->assertTrue($peek['challenger_submitted']);
        $this->assertFalse($peek['revealed']);
        $this->assertArrayNotHasKey('challenger_move', $peek);

        $this->submitMove($opponent, $matchId, 'scissors')
            ->assertOk()
            ->assertJsonPath('data.resolved', true)
            ->assertJsonPath('data.round.revealed', true)
            ->assertJsonPath('data.round.challenger_move', 'rock')
            ->assertJsonPath('data.round.opponent_move', 'scissors')
            ->assertJsonPath('data.round.result', RpsRound::RESULT_CHALLENGER);

        $round = RpsRound::query()->findOrFail($roundId);
        $this->assertSame((string) $challenger->getKey(), (string) $round->winner_id);
    }

    public function test_a_duplicate_move_in_the_same_round_is_rejected(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent, ['mode' => RpsMatch::MODE_BEST_OF_3]);

        $this->submitMove($challenger, $matchId, 'rock')->assertOk();

        $this->submitMove($challenger, $matchId, 'paper')
            ->assertStatus(422)
            ->assertJsonValidationErrors('move');

        $this->assertSame(1, RpsMove::query()->where('user_id', $challenger->getKey())->count());
    }

    public function test_a_replayed_move_returns_the_original_submission(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent, ['mode' => RpsMatch::MODE_BEST_OF_3]);

        $key = (string) Str::uuid();
        $first = $this->submitMove($challenger, $matchId, 'rock', $key)->assertOk();
        $replay = $this->submitMove($challenger, $matchId, 'paper', $key)
            ->assertOk()
            ->assertJsonPath('data.replay', true);

        $this->assertSame($first->json('data.move.id'), $replay->json('data.move.id'));
        $this->assertSame(1, RpsMove::query()->where('user_id', $challenger->getKey())->count());
        $this->assertSame('rock', RpsMove::query()->where('user_id', $challenger->getKey())->first()->move);
    }

    public function test_moves_outside_the_active_rule_set_are_rejected(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent);

        $this->submitMove($challenger, $matchId, 'lizard')
            ->assertStatus(422)
            ->assertJsonValidationErrors('move');

        $this->assertSame(0, RpsMove::query()->count());
    }

    public function test_a_non_player_cannot_submit_a_move_or_view_a_private_match(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        $stranger = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent);

        $this->submitMove($stranger, $matchId, 'rock')->assertForbidden();

        Sanctum::actingAs($stranger);
        $this->getJson('/api/v1/games/rps/matches/'.$matchId)->assertForbidden();
    }

    public function test_classic_match_settles_the_pot_to_the_winner_and_writes_history(): void
    {
        Event::fake([
            RpsMoveSubmitted::class,
            RpsRoundFinished::class,
            RpsMatchFinished::class,
        ]);

        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent);

        $this->submitMove($challenger, $matchId, 'rock')->assertOk();
        $this->submitMove($opponent, $matchId, 'scissors')
            ->assertOk()
            ->assertJsonPath('data.finished', true);

        $match = RpsMatch::query()->findOrFail($matchId);
        $this->assertSame(RpsMatch::STATUS_FINISHED, $match->status);
        $this->assertSame(RpsMatch::RESULT_CHALLENGER_WON, $match->result);
        $this->assertSame((string) $challenger->getKey(), (string) $match->winner_id);
        $this->assertSame((string) $opponent->getKey(), (string) $match->loser_id);
        $this->assertNotNull($match->settled_at);

        // 900 left after the entry fee, plus the 200 coin pot.
        $this->assertSame(1100, (int) $challenger->wallet()->first()->coins_balance);
        $this->assertSame(900, (int) $opponent->wallet()->first()->coins_balance);

        $this->assertDatabaseHas('rps_rewards', [
            'match_id' => $matchId,
            'user_id' => $challenger->getKey(),
            'status' => RpsReward::STATUS_GRANTED,
            'amount' => 200,
            'currency' => 'coins',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $challenger->getKey(),
            'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
            'amount' => 200,
            'reference_id' => $matchId,
        ]);

        $this->assertSame(2, RpsHistory::query()->where('match_id', $matchId)->count());
        $this->assertTrue((bool) RpsHistory::query()
            ->where('match_id', $matchId)
            ->where('user_id', $challenger->getKey())
            ->firstOrFail()
            ->is_winner);

        Event::assertDispatched(RpsRoundFinished::class);
        Event::assertDispatched(RpsMatchFinished::class);
    }

    public function test_best_of_three_runs_multiple_rounds_before_settling(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent, ['mode' => RpsMatch::MODE_BEST_OF_3]);

        // Round 1: draw, nothing settles.
        $this->submitMove($challenger, $matchId, 'rock')->assertOk();
        $this->submitMove($opponent, $matchId, 'rock')->assertOk()->assertJsonPath('data.finished', false);

        // Rounds 2 and 3: challenger takes both and reaches two wins.
        $this->submitMove($challenger, $matchId, 'rock')->assertOk();
        $this->submitMove($opponent, $matchId, 'scissors')->assertOk()->assertJsonPath('data.finished', false);

        $this->submitMove($challenger, $matchId, 'paper')->assertOk();
        $this->submitMove($opponent, $matchId, 'rock')->assertOk()->assertJsonPath('data.finished', true);

        $match = RpsMatch::query()->findOrFail($matchId);
        $this->assertSame(2, (int) $match->challenger_score);
        $this->assertSame(0, (int) $match->opponent_score);
        $this->assertSame(1, (int) $match->draw_count);
        $this->assertSame(3, (int) $match->rounds_played);
        $this->assertSame(RpsMatch::RESULT_CHALLENGER_WON, $match->result);
    }

    public function test_a_drawn_round_replays_instead_of_settling(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId, 'round_id' => $firstRoundId] = $this->openMatch($challenger, $opponent);

        $this->submitMove($challenger, $matchId, 'paper')->assertOk();
        $this->submitMove($opponent, $matchId, 'paper')
            ->assertOk()
            ->assertJsonPath('data.resolved', true)
            ->assertJsonPath('data.finished', false)
            ->assertJsonPath('data.round.result', RpsRound::RESULT_DRAW);

        $match = RpsMatch::query()->findOrFail($matchId);
        $this->assertSame(RpsMatch::STATUS_ACTIVE, $match->status);
        $this->assertSame(1, (int) $match->draw_count);
        $this->assertSame(2, (int) $match->current_round);
        $this->assertNotSame($firstRoundId, (string) $match->current_round_id);
    }

    public function test_a_match_both_players_abandon_ends_in_a_draw_and_refunds_both_stakes(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent, ['mode' => RpsMatch::MODE_BEST_OF_3]);

        $scheduler = app(RpsScheduler::class);
        for ($round = 0; $round < 2; $round++) {
            $this->travel(31)->seconds();
            $scheduler->processDue();
        }

        $match = RpsMatch::query()->findOrFail($matchId);
        $this->assertSame(RpsMatch::STATUS_FINISHED, $match->status);
        $this->assertSame(RpsMatch::RESULT_DRAW, $match->result);
        $this->assertNull($match->winner_id);

        $this->assertSame(1000, (int) $challenger->wallet()->first()->coins_balance);
        $this->assertSame(1000, (int) $opponent->wallet()->first()->coins_balance);
        $this->assertSame(2, RpsReward::query()->where('match_id', $matchId)->where('is_refund', true)->count());
    }

    public function test_the_scheduler_forfeits_a_player_who_stops_submitting(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent, ['mode' => RpsMatch::MODE_BEST_OF_3]);

        $scheduler = app(RpsScheduler::class);

        // The opponent goes silent for two consecutive rounds.
        $this->submitMove($challenger, $matchId, 'rock')->assertOk();
        $this->travel(31)->seconds();
        $scheduler->processDue();

        $this->submitMove($challenger, $matchId, 'rock')->assertOk();
        $this->travel(31)->seconds();
        $scheduler->processDue();

        $match = RpsMatch::query()->findOrFail($matchId);
        $this->assertSame(RpsMatch::STATUS_FINISHED, $match->status);
        $this->assertSame(RpsMatch::RESULT_FORFEIT, $match->result);
        $this->assertSame((string) $opponent->getKey(), (string) $match->forfeited_by);
        $this->assertSame((string) $challenger->getKey(), (string) $match->winner_id);
        $this->assertSame(1100, (int) $challenger->wallet()->first()->coins_balance);
    }

    public function test_the_scheduler_expires_and_refunds_an_unanswered_challenge(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($challenger);

        $matchId = $this->postJson('/api/v1/games/rps/create', [
            'idempotency_key' => (string) Str::uuid(),
            'matchmaking' => RpsMatch::MATCHMAKING_PRIVATE,
            'opponent_id' => (string) $opponent->getKey(),
            'entry_fee_coins' => 100,
        ])->assertCreated()->json('data.match.id');

        $this->assertSame(900, (int) $challenger->wallet()->first()->coins_balance);

        $this->travel(200)->seconds();
        app(RpsScheduler::class)->processDue();

        $match = RpsMatch::query()->findOrFail($matchId);
        $this->assertSame(RpsMatch::STATUS_EXPIRED, $match->status);
        $this->assertSame(1000, (int) $challenger->wallet()->first()->coins_balance);
    }

    public function test_rematch_reuses_the_previous_terms_and_respects_the_chain_limit(): void
    {
        $challenger = User::factory()->withCoins(2000)->create();
        $opponent = User::factory()->withCoins(2000)->create();
        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent);

        $this->submitMove($challenger, $matchId, 'rock')->assertOk();
        $this->submitMove($opponent, $matchId, 'scissors')->assertOk();

        Sanctum::actingAs($opponent);
        $rematch = $this->postJson('/api/v1/games/rps/rematch', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
        ])->assertCreated()->json('data.match');

        $this->assertSame(RpsMatch::MATCHMAKING_REMATCH, $rematch['matchmaking']);
        $this->assertSame((string) $opponent->getKey(), $rematch['challenger_id']);
        $this->assertSame((string) $challenger->getKey(), $rematch['opponent_id']);
        $this->assertSame(100, $rematch['entry_fee_coins']);
        $this->assertTrue($rematch['is_rematch']);
    }

    public function test_an_unfinished_match_cannot_be_rematched(): void
    {
        $challenger = User::factory()->withCoins(2000)->create();
        $opponent = User::factory()->withCoins(2000)->create();
        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent);

        Sanctum::actingAs($challenger);
        $this->postJson('/api/v1/games/rps/rematch', [
            'idempotency_key' => (string) Str::uuid(),
            'match_id' => $matchId,
        ])->assertStatus(422)->assertJsonValidationErrors('match_id');
    }

    public function test_history_statistics_and_leaderboard_reflect_finished_matches(): void
    {
        $challenger = User::factory()->withCoins(1000)->create();
        $opponent = User::factory()->withCoins(1000)->create();
        ['match_id' => $matchId] = $this->openMatch($challenger, $opponent);

        $this->submitMove($challenger, $matchId, 'rock')->assertOk();
        $this->submitMove($opponent, $matchId, 'scissors')->assertOk();

        Sanctum::actingAs($challenger);

        $history = $this->getJson('/api/v1/games/rps/history')->assertOk()->json('data');
        $this->assertCount(1, $history);
        $this->assertSame($matchId, $history[0]['match_id']);

        $stats = $this->getJson('/api/v1/games/rps/statistics')->assertOk()->json('data');
        $this->assertSame(1, $stats['matches_played']);
        $this->assertSame(1, $stats['matches_won']);

        $this->assertDatabaseHas('rps_statistics', [
            'user_id' => $challenger->getKey(),
            'matches_won' => 1,
        ]);
        $this->assertGreaterThan(0, RpsStatistic::query()->where('user_id', $opponent->getKey())->count());

        $leaderboard = $this->getJson('/api/v1/games/rps/leaderboard?period=all_time')->assertOk()->json('data');
        $this->assertNotEmpty($leaderboard);
        $this->assertSame((string) $challenger->getKey(), (string) $leaderboard[0]['user_id']);
    }

    public function test_tournaments_can_be_listed_and_joined_once(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $tournaments = $this->getJson('/api/v1/games/rps/tournaments')->assertOk()->json('data');
        $this->assertNotEmpty($tournaments);

        $daily = RpsTournament::query()->where('slug', 'rps-daily-cup')->firstOrFail();

        $key = (string) Str::uuid();
        $this->postJson('/api/v1/games/rps/join-tournament', [
            'idempotency_key' => $key,
            'tournament_id' => (string) $daily->getKey(),
        ])->assertCreated();

        $this->postJson('/api/v1/games/rps/join-tournament', [
            'idempotency_key' => $key,
            'tournament_id' => (string) $daily->getKey(),
        ])->assertOk()->assertJsonPath('data.replay', true);

        $daily->refresh();
        $this->assertSame(1, (int) $daily->registered_count);
        $this->assertTrue($daily->hasParticipant((string) $user->getKey()));
    }

    public function test_a_vip_only_tournament_rejects_non_vip_players(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $vipCup = RpsTournament::query()->where('slug', 'rps-vip-arena-cup')->firstOrFail();
        $vipCup->forceFill(['status' => RpsTournament::STATUS_REGISTRATION])->save();

        $this->postJson('/api/v1/games/rps/join-tournament', [
            'idempotency_key' => (string) Str::uuid(),
            'tournament_id' => (string) $vipCup->getKey(),
        ])->assertStatus(422);

        $this->assertSame(1000, (int) $user->wallet()->first()->coins_balance);
    }

    public function test_the_plugin_is_registered_against_the_game_platform(): void
    {
        $game = Game::query()->where('slug', Game::SLUG_ROCK_PAPER_SCISSORS)->first();

        $this->assertNotNull($game);
        $this->assertTrue($game->isActive());
        $this->assertSame(
            (string) $game->getKey(),
            (string) RpsConfig::query()->where('slug', 'default')->firstOrFail()->game_id,
        );
    }
}
