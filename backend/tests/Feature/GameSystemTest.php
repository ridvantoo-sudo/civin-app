<?php

namespace Tests\Feature;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameMatch;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\LotteryTicket;
use App\Features\Games\Models\QuizQuestion;
use App\Features\Games\Models\RedEnvelope;
use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Database\Seeders\GamePlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GamePlatformSeeder::class);
    }

    public function test_lists_catalog_with_all_ten_slugs(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/games')
            ->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        foreach (Game::slugs() as $slug) {
            $this->assertContains($slug, $slugs);
        }

        $this->assertCount(10, $slugs);
    }

    public function test_play_lucky_wheel_debits_credits_and_records_history(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_LUCKY_WHEEL,
            'bet_coins' => 100,
            'input' => [],
        ])->assertCreated();

        $play = $response->json('data.play');
        $this->assertSame(100, $play['bet_coins']);
        $this->assertContains($play['result'], [
            GamePlay::RESULT_WON,
            GamePlay::RESULT_LOST,
            GamePlay::RESULT_DRAW,
        ]);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $expected = 1000 - 100 + (int) $play['reward_coins'];
        $this->assertSame($expected, $wallet->coins_balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => GamePlay::WALLET_TYPE_GAME_BET,
            'amount' => -100,
        ]);

        $this->getJson('/api/v1/games/history')
            ->assertOk()
            ->assertJsonFragment(['id' => $play['id']]);
    }

    public function test_insufficient_coins_rejected(): void
    {
        $user = User::factory()->withCoins(5)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_LUCKY_WHEEL,
            'bet_coins' => 100,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['coins']);
    }

    public function test_quiz_correct_answer_wins(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $question = QuizQuestion::query()->create([
            'question' => 'Civin test question: capital of Italy?',
            'options' => ['Milan', 'Rome', 'Naples', 'Turin'],
            'correct_index' => 1,
            'difficulty' => 'easy',
            'bet_coins' => 10,
            'reward_coins' => 30,
            'status' => QuizQuestion::STATUS_ACTIVE,
        ]);

        $response = $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_QUIZ,
            'bet_coins' => 10,
            'input' => [
                'question_id' => $question->id,
                'answer_index' => 1,
            ],
        ])->assertCreated();

        $play = $response->json('data.play');
        $this->assertSame(GamePlay::RESULT_WON, $play['result']);
        $this->assertTrue($play['outcome']['correct']);
        $this->assertGreaterThan(0, $play['reward_coins']);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1000 - (int) $play['bet_coins'] + (int) $play['reward_coins'], $wallet->coins_balance);
    }

    public function test_red_envelope_create_and_claim(): void
    {
        $sender = User::factory()->withCoins(1000)->create();
        $claimer = User::factory()->withCoins(100)->create();

        Sanctum::actingAs($sender);
        $create = $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_RED_ENVELOPE,
            'input' => [
                'action' => 'create',
                'total_coins' => 200,
                'total_slots' => 2,
                'message' => 'Good luck',
            ],
        ])->assertCreated();

        $envelopeId = $create->json('data.outcome.envelope_id');
        $this->assertNotEmpty($envelopeId);

        $senderWallet = Wallet::query()->where('user_id', $sender->id)->firstOrFail();
        $this->assertSame(800, $senderWallet->coins_balance);

        $envelope = RedEnvelope::query()->findOrFail($envelopeId);
        $this->assertSame(200, $envelope->remaining_coins);
        $this->assertSame(2, $envelope->remaining_slots);

        Sanctum::actingAs($claimer);
        $claim = $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_RED_ENVELOPE,
            'input' => [
                'action' => 'claim',
                'envelope_id' => $envelopeId,
            ],
        ])->assertCreated();

        $claimed = (int) $claim->json('data.play.reward_coins');
        $this->assertGreaterThan(0, $claimed);
        $this->assertSame(GamePlay::RESULT_CLAIMED, $claim->json('data.play.result'));

        $this->assertDatabaseHas('red_envelope_claims', [
            'envelope_id' => $envelopeId,
            'user_id' => $claimer->id,
            'coins' => $claimed,
        ]);

        $claimerWallet = Wallet::query()->where('user_id', $claimer->id)->firstOrFail();
        $this->assertSame(100 + $claimed, $claimerWallet->coins_balance);

        $envelope->refresh();
        $this->assertSame(200 - $claimed, $envelope->remaining_coins);
        $this->assertSame(1, $envelope->remaining_slots);
    }

    public function test_lottery_buy_ticket(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/games/lottery/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'open');

        $response = $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_LOTTERY,
            'input' => [
                'action' => 'buy',
                'numbers' => [1, 2, 3, 4, 5, 6],
            ],
        ])->assertCreated();

        $this->assertSame(50, $response->json('data.play.bet_coins'));
        $this->assertDatabaseHas('lottery_tickets', [
            'user_id' => $user->id,
            'coins_paid' => 50,
            'status' => LotteryTicket::STATUS_PENDING,
        ]);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(950, $wallet->coins_balance);
    }

    public function test_rps_play_against_house(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_ROCK_PAPER_SCISSORS,
            'bet_coins' => 50,
            'input' => ['choice' => 'rock'],
        ])->assertCreated();

        $play = $response->json('data.play');
        $this->assertSame(50, $play['bet_coins']);
        $this->assertSame('rock', $play['outcome']['player']);
        $this->assertContains($play['outcome']['house'], ['rock', 'paper', 'scissors']);
        $this->assertContains($play['result'], [
            GamePlay::RESULT_WON,
            GamePlay::RESULT_LOST,
            GamePlay::RESULT_DRAW,
        ]);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1000 - 50 + (int) $play['reward_coins'], $wallet->coins_balance);
    }

    public function test_ludo_create_join_and_roll(): void
    {
        $host = User::factory()->withCoins(1000)->create();
        $guest = User::factory()->withCoins(1000)->create();
        Profile::factory()->create(['user_id' => $host->id, 'display_name' => $host->username]);
        Profile::factory()->create(['user_id' => $guest->id, 'display_name' => $guest->username]);

        Sanctum::actingAs($host);
        $created = $this->postJson('/api/v1/games/matches', [
            'game_slug' => Game::SLUG_LUDO,
            'stake_coins' => 100,
            'max_players' => 2,
        ])->assertCreated();

        $matchId = $created->json('data.id');
        $this->assertSame(GameMatch::STATUS_WAITING, $created->json('data.status'));
        $this->assertSame(900, Wallet::query()->where('user_id', $host->id)->value('coins_balance'));

        Sanctum::actingAs($guest);
        $joined = $this->postJson("/api/v1/games/matches/{$matchId}/join")
            ->assertOk();

        $this->assertSame(GameMatch::STATUS_ACTIVE, $joined->json('data.status'));
        $this->assertSame(900, Wallet::query()->where('user_id', $guest->id)->value('coins_balance'));
        $this->assertCount(2, $joined->json('data.players'));

        Sanctum::actingAs($host);
        $rolled = $this->postJson("/api/v1/games/matches/{$matchId}/action", [
            'action' => 'roll',
        ])->assertOk();

        $state = $rolled->json('data.state');
        $this->assertArrayHasKey('last_roll', $state);
        $this->assertSame($host->id, $state['last_roll']['user_id']);
        $this->assertGreaterThanOrEqual(1, $state['last_roll']['dice']);
        $this->assertLessThanOrEqual(6, $state['last_roll']['dice']);
        $this->assertGreaterThanOrEqual(1, $state['positions'][$host->id]);
        $this->assertSame(1, $state['current_turn']);
    }

    public function test_leaderboard_and_analytics(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Profile::factory()->create([
            'user_id' => $user->id,
            'display_name' => 'Gamer One',
            'avatar_url' => 'https://cdn.civin.app/avatars/gamer.png',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_QUIZ,
            'bet_coins' => 10,
            'input' => [
                'question_id' => QuizQuestion::query()->where('question', 'What is 2 + 2?')->value('id'),
                'answer_index' => 1,
            ],
        ])->assertCreated();

        $this->postJson('/api/v1/games/analytics', [
            'event' => 'game_open',
            'game_slug' => Game::SLUG_QUIZ,
            'payload' => ['source' => 'test'],
        ])->assertCreated()
            ->assertJsonPath('data.tracked', true);

        $this->assertDatabaseHas('game_analytics_events', [
            'user_id' => $user->id,
            'event' => 'game_open',
        ]);

        $this->getJson('/api/v1/games/leaderboard?game_slug=quiz&period=daily')
            ->assertOk()
            ->assertJsonFragment([
                'user_id' => $user->id,
                'display_name' => 'Gamer One',
                'avatar_url' => 'https://cdn.civin.app/avatars/gamer.png',
            ]);

        $this->getJson('/api/v1/games/stats?game_slug=quiz')
            ->assertOk()
            ->assertJsonPath('data.plays', 1);
    }
}
