<?php

namespace Tests\Feature;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Plugins\Quiz\Events\QuizCreated;
use App\Features\Games\Plugins\Quiz\Events\QuizFinished;
use App\Features\Games\Plugins\Quiz\Events\QuizJoined;
use App\Features\Games\Plugins\Quiz\Events\QuizStarted;
use App\Features\Games\Plugins\Quiz\Models\QuizConfig;
use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use App\Features\Games\Plugins\Quiz\Models\QuizQuestion;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Models\QuizTournament;
use App\Features\Games\Plugins\Quiz\Scheduler\QuizScheduler;
use App\Features\Games\Plugins\Quiz\Support\QuizScoringEngine;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Database\Seeders\GamePlatformSeeder;
use Database\Seeders\QuizSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuizPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GamePlatformSeeder::class);
        $this->seed(QuizSeeder::class);
    }

    public function test_config_endpoint_returns_modes_and_scoring(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/games/quiz/config')
            ->assertOk()
            ->assertJsonPath('data.slug', 'default')
            ->assertJsonPath('data.enabled', true);

        $this->assertContains('classic', $response->json('data.modes_enabled'));
        $this->assertContains('tournament', $response->json('data.modes_enabled'));
        $this->assertArrayHasKey('correct_points', $response->json('data.scoring_rules'));
    }

    public function test_categories_and_questions_endpoints(): void
    {
        $user = User::factory()->withCoins(500)->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/games/quiz/categories')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'name']]]);

        $questions = $this->getJson('/api/v1/games/quiz/questions')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($questions);
        $this->assertArrayNotHasKey('correct_index', $questions[0]);
    }

    public function test_create_join_start_answer_settles_and_is_idempotent(): void
    {
        $host = User::factory()->withCoins(1000)->create();
        $guest = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($host);

        $createKey = (string) Str::uuid();
        $create = $this->postJson('/api/v1/games/quiz/create', [
            'idempotency_key' => $createKey,
            'mode' => 'classic',
            'difficulty' => 'easy',
            'reward_type' => 'coins',
            'reward_pool' => 100,
            'bet_coins' => 10,
            'question_count' => 1,
            'time_per_question' => 30,
            'winner_count' => 1,
        ])->assertCreated();

        $sessionId = $create->json('data.session.id');
        $this->assertFalse($create->json('data.replay'));

        // Idempotent replay
        $this->postJson('/api/v1/games/quiz/create', [
            'idempotency_key' => $createKey,
            'mode' => 'classic',
            'difficulty' => 'easy',
            'reward_type' => 'coins',
            'question_count' => 1,
            'winner_count' => 1,
        ])->assertOk()->assertJsonPath('data.replay', true);

        Sanctum::actingAs($guest);
        $joinKey = (string) Str::uuid();
        $this->postJson('/api/v1/games/quiz/join', [
            'idempotency_key' => $joinKey,
            'session_id' => $sessionId,
        ])->assertCreated();

        Sanctum::actingAs($host);
        $start = $this->postJson('/api/v1/games/quiz/start', [
            'idempotency_key' => (string) Str::uuid(),
            'session_id' => $sessionId,
        ])->assertOk();

        $questionId = $start->json('data.question.id');
        $this->assertNotEmpty($questionId);

        $question = QuizQuestion::query()->findOrFail($questionId);
        $answerKey = (string) Str::uuid();
        $answer = $this->postJson('/api/v1/games/quiz/answer', [
            'idempotency_key' => $answerKey,
            'session_id' => $sessionId,
            'question_id' => $questionId,
            'selected_index' => (int) $question->correct_index,
            'client_response_ms' => 500,
        ])->assertOk();

        $this->assertTrue($answer->json('data.answer.is_correct'));
        $this->assertGreaterThan(0, $answer->json('data.answer.points_awarded'));

        // Duplicate answer blocked
        $this->postJson('/api/v1/games/quiz/answer', [
            'idempotency_key' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'question_id' => $questionId,
            'selected_index' => (int) $question->correct_index,
        ])->assertStatus(422);

        // Idempotent answer replay
        $this->postJson('/api/v1/games/quiz/answer', [
            'idempotency_key' => $answerKey,
            'session_id' => $sessionId,
            'question_id' => $questionId,
            'selected_index' => (int) $question->correct_index,
        ])->assertOk()->assertJsonPath('data.replay', true);

        // Guest answers to finish session (1 question)
        Sanctum::actingAs($guest);
        $this->postJson('/api/v1/games/quiz/answer', [
            'idempotency_key' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'question_id' => $questionId,
            'selected_index' => (int) $question->correct_index,
            'client_response_ms' => 800,
        ])->assertOk()->assertJsonPath('data.replay', false);

        $this->assertDatabaseHas('quiz_sessions', [
            'id' => $sessionId,
            'status' => QuizSession::STATUS_FINISHED,
        ]);
        $this->assertDatabaseHas('quiz_answers', [
            'session_id' => $sessionId,
            'user_id' => $host->id,
            'is_correct' => true,
        ]);
        $this->assertDatabaseHas('quiz_scores', [
            'session_id' => $sessionId,
            'user_id' => $host->id,
        ]);
        $this->assertDatabaseHas('quiz_histories', [
            'session_id' => $sessionId,
            'user_id' => $host->id,
        ]);
        $this->assertDatabaseHas('game_plays', [
            'user_id' => $host->id,
            'bet_coins' => 10,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $host->id,
            'type' => GamePlay::WALLET_TYPE_GAME_BET,
        ]);

        $this->getJson('/api/v1/games/quiz/history')->assertOk();
        $this->getJson('/api/v1/games/quiz/leaderboard?period=daily')->assertOk();
        $this->getJson('/api/v1/games/quiz/statistics')->assertOk();
        $this->getJson('/api/v1/games/quiz/tournaments')->assertOk();
    }

    public function test_client_cannot_supply_score_or_correct_flag(): void
    {
        $user = User::factory()->withCoins(500)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/quiz/answer', [
            'idempotency_key' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'question_id' => (string) Str::uuid(),
            'selected_index' => 0,
            'is_correct' => true,
            'points' => 9999,
        ])->assertStatus(422);
    }

    public function test_scoring_engine_awards_speed_and_streak(): void
    {
        $engine = new QuizScoringEngine;
        $session = new QuizSession([
            'time_per_question' => 10,
            'mode' => QuizSession::MODE_CLASSIC,
            'scoring_rules' => QuizConfig::DEFAULT_SCORING,
        ]);
        $player = new QuizPlayer(['streak' => 2]);
        $question = new QuizQuestion([
            'type' => 'multiple_choice',
            'correct_index' => 1,
            'options' => ['a', 'b'],
        ]);

        $result = $engine->scoreAnswer($session, $player, $question, 1, null, 1000, false);
        $this->assertTrue($result['is_correct']);
        $this->assertGreaterThan(100, $result['points']);
        $this->assertGreaterThan(0, $result['speed_bonus']);
        $this->assertGreaterThan(0, $result['streak_bonus']);
    }

    public function test_legacy_engine_play_still_works(): void
    {
        $user = User::factory()->withCoins(500)->create();
        Sanctum::actingAs($user);

        $question = QuizQuestion::query()->where('status', 'active')->firstOrFail();

        $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_QUIZ,
            'bet_coins' => 10,
            'input' => [
                'question_id' => $question->id,
                'answer_index' => (int) $question->correct_index,
            ],
        ])->assertSuccessful();

        $this->assertDatabaseHas('game_plays', [
            'user_id' => $user->id,
            'bet_coins' => 10,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => GamePlay::WALLET_TYPE_GAME_BET,
            'amount' => -10,
        ]);
    }

    public function test_answer_after_timeout_is_rejected(): void
    {
        $host = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($host);

        $create = $this->postJson('/api/v1/games/quiz/create', [
            'idempotency_key' => (string) Str::uuid(),
            'mode' => 'classic',
            'difficulty' => 'easy',
            'reward_type' => 'coins',
            'reward_pool' => 50,
            'question_count' => 1,
            'time_per_question' => 30,
            'winner_count' => 1,
        ])->assertCreated();

        $sessionId = $create->json('data.session.id');
        $start = $this->postJson('/api/v1/games/quiz/start', [
            'idempotency_key' => (string) Str::uuid(),
            'session_id' => $sessionId,
        ])->assertOk();

        QuizSession::query()->whereKey($sessionId)->update([
            'question_ends_at' => now()->subSecond(),
        ]);

        $this->postJson('/api/v1/games/quiz/answer', [
            'idempotency_key' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'question_id' => $start->json('data.question.id'),
            'selected_index' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors(['question_id']);
    }

    public function test_scheduler_advances_timed_out_question(): void
    {
        $host = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($host);

        $ids = QuizQuestion::query()
            ->where('status', QuizQuestion::STATUS_ACTIVE)
            ->where('approval_status', QuizQuestion::APPROVAL_APPROVED)
            ->limit(2)
            ->pluck('id')
            ->values()
            ->all();
        $this->assertCount(2, $ids);

        $create = $this->postJson('/api/v1/games/quiz/create', [
            'idempotency_key' => (string) Str::uuid(),
            'mode' => 'classic',
            'difficulty' => 'easy',
            'reward_type' => 'coins',
            'reward_pool' => 50,
            'question_count' => 2,
            'question_ids' => $ids,
            'time_per_question' => 30,
            'winner_count' => 1,
        ])->assertCreated();

        $sessionId = $create->json('data.session.id');
        $this->postJson('/api/v1/games/quiz/start', [
            'idempotency_key' => (string) Str::uuid(),
            'session_id' => $sessionId,
        ])->assertOk();

        $before = QuizSession::query()->findOrFail($sessionId);
        QuizSession::query()->whereKey($sessionId)->update([
            'question_ends_at' => now()->subSecond(),
        ]);

        $result = app(QuizScheduler::class)->processDue(20);
        $this->assertGreaterThanOrEqual(1, $result['advanced']);

        $after = QuizSession::query()->findOrFail($sessionId);
        $this->assertNotSame($before->current_question_id, $after->current_question_id);
    }

    public function test_settle_credits_winner_wallet(): void
    {
        $host = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($host);

        $create = $this->postJson('/api/v1/games/quiz/create', [
            'idempotency_key' => (string) Str::uuid(),
            'mode' => 'classic',
            'difficulty' => 'easy',
            'reward_type' => 'coins',
            'reward_pool' => 200,
            'bet_coins' => 0,
            'question_count' => 1,
            'time_per_question' => 30,
            'winner_count' => 1,
        ])->assertCreated();

        $sessionId = $create->json('data.session.id');
        $start = $this->postJson('/api/v1/games/quiz/start', [
            'idempotency_key' => (string) Str::uuid(),
            'session_id' => $sessionId,
        ])->assertOk();

        $question = QuizQuestion::query()->findOrFail($start->json('data.question.id'));
        $this->postJson('/api/v1/games/quiz/answer', [
            'idempotency_key' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'question_id' => $question->id,
            'selected_index' => (int) $question->correct_index,
            'client_response_ms' => 200,
        ])->assertOk();

        $this->assertSame(QuizSession::STATUS_FINISHED, QuizSession::query()->findOrFail($sessionId)->status);
        $this->assertSame(1000, Wallet::query()->where('user_id', $host->id)->firstOrFail()->coins_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $host->id,
            'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
            'amount' => 200,
        ]);
        $this->assertDatabaseHas('quiz_rewards', [
            'session_id' => $sessionId,
            'user_id' => $host->id,
        ]);
    }

    public function test_tournaments_list_and_realtime_events(): void
    {
        QuizTournament::query()->create([
            'slug' => 'cup-'.Str::random(6),
            'name' => 'Quiz Cup',
            'status' => QuizTournament::STATUS_SCHEDULED,
            'rounds' => 2,
            'max_players' => 32,
            'entry_fee_coins' => 0,
            'reward_type' => 'coins',
            'reward_pool' => 500,
            'scheduled_at' => now()->addDay(),
        ]);

        Event::fake([QuizCreated::class, QuizJoined::class, QuizStarted::class, QuizFinished::class]);

        $host = User::factory()->withCoins(500)->create();
        Sanctum::actingAs($host);

        $this->getJson('/api/v1/games/quiz/tournaments')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Quiz Cup']);

        $created = $this->postJson('/api/v1/games/quiz/create', [
            'idempotency_key' => (string) Str::uuid(),
            'mode' => 'classic',
            'difficulty' => 'easy',
            'reward_type' => 'coins',
            'reward_pool' => 0,
            'question_count' => 1,
            'winner_count' => 1,
        ])->assertCreated();
        Event::assertDispatched(QuizCreated::class);

        $guest = User::factory()->withCoins(100)->create();
        Sanctum::actingAs($guest);
        $this->postJson('/api/v1/games/quiz/join', [
            'idempotency_key' => (string) Str::uuid(),
            'session_id' => $created->json('data.session.id'),
        ])->assertCreated();
        Event::assertDispatched(QuizJoined::class);

        Sanctum::actingAs($host);
        $this->postJson('/api/v1/games/quiz/start', [
            'idempotency_key' => (string) Str::uuid(),
            'session_id' => $created->json('data.session.id'),
        ])->assertOk();
        Event::assertDispatched(QuizStarted::class);
    }
}
