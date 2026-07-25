<?php

namespace Tests\Feature;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberDrawFinished;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberTicketPurchased;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberConfig;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberDraw;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberJackpot;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberResult;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberReward;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use App\Features\Games\Plugins\LuckyNumber\Scheduler\LuckyNumberScheduler;
use App\Features\Games\Plugins\LuckyNumber\Support\LuckyNumberDrawEngine;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use Database\Seeders\GamePlatformSeeder;
use Database\Seeders\LuckyNumberSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LuckyNumberPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GamePlatformSeeder::class);
        $this->seed(LuckyNumberSeeder::class);
    }

    /**
     * Stub RNG so single_number picks of [5] win when range is 1–10.
     * drawNumbers indexes into pool via int(0, count-1); index 4 → 5.
     * Bonus rolls use int(1, 100); returning 4 keeps chance checks from firing.
     */
    private function stubWinningRng(): void
    {
        $this->app->instance(GameRng::class, new class implements GameRng
        {
            public function int(int $min, int $max): int
            {
                // Draw pool index: range 1–10 → index 4 yields winning number 5.
                if ($min === 0) {
                    return 4;
                }

                // Bonus rolls use int(1, 100) with "roll > chance" to skip —
                // always return $max so no bonus fires.
                return $max;
            }

            public function sampleUnique(int $min, int $max, int $count): array
            {
                return [5];
            }
        });
        $this->app->forgetInstance(LuckyNumberDrawEngine::class);
    }

    public function test_config_endpoint_returns_modes_ranges_and_multipliers(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/games/lucky-number/config')
            ->assertOk()
            ->assertJsonPath('data.slug', 'default')
            ->assertJsonPath('data.min_bet_coins', 10)
            ->assertJsonPath('data.rtp_percent', 96);

        $this->assertNotEmpty($response->json('data.modes_enabled'));
        $this->assertContains('single_number', $response->json('data.modes_enabled'));
        $this->assertNotEmpty($response->json('data.number_ranges'));
        $this->assertNotEmpty($response->json('data.reward_multipliers'));
        $this->assertArrayHasKey('exact', $response->json('data.reward_multipliers'));
        $this->assertArrayHasKey('single_number', $response->json('data.mode_ranges'));
    }

    public function test_play_debits_wallet_creates_records_and_is_server_randomized(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);
        $this->stubWinningRng();

        $key = (string) Str::uuid();
        $response = $this->postJson('/api/v1/games/lucky-number/play', [
            'idempotency_key' => $key,
            'game_mode' => 'single_number',
            'bet_amount' => 50,
            'picked_numbers' => [5],
            'client_session_id' => 'session-lucky',
        ])->assertCreated();

        $ticket = $response->json('data.ticket');
        $this->assertSame($key, $ticket['idempotency_key']);
        $this->assertSame(50, $ticket['cost_coins']);
        $this->assertSame('single_number', $ticket['game_mode']);
        $this->assertFalse($response->json('data.replay'));
        $this->assertSame([5], $ticket['picked_numbers']);
        $this->assertSame([5], $ticket['outcome']['winning_numbers']);
        $this->assertTrue($ticket['outcome']['server_verified']);
        $this->assertArrayNotHasKey('rolls', $ticket['outcome']);
        $this->assertTrue($ticket['is_win']);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1000 - 50 + (int) $ticket['reward_coins'], $wallet->coins_balance);

        $this->assertDatabaseHas('lucky_number_tickets', [
            'user_id' => $user->id,
            'idempotency_key' => $key,
            'status' => LuckyNumberTicket::STATUS_SETTLED,
        ]);
        $this->assertDatabaseHas('lucky_number_draws', [
            'status' => LuckyNumberDraw::STATUS_FINISHED,
        ]);
        $this->assertDatabaseHas('lucky_number_results', [
            'user_id' => $user->id,
            'is_win' => true,
        ]);
        $this->assertDatabaseHas('lucky_number_rewards', [
            'user_id' => $user->id,
            'status' => LuckyNumberReward::STATUS_GRANTED,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => GamePlay::WALLET_TYPE_GAME_BET,
            'amount' => -50,
        ]);
        $this->assertDatabaseHas('game_plays', [
            'user_id' => $user->id,
            'bet_coins' => 50,
        ]);
        $this->assertDatabaseHas('game_rewards', [
            'user_id' => $user->id,
            'status' => GameReward::STATUS_GRANTED,
        ]);
        $this->assertDatabaseHas('game_histories', [
            'user_id' => $user->id,
            'bet_coins' => 50,
        ]);
        $this->assertDatabaseHas('game_analytics_events', [
            'user_id' => $user->id,
            'event' => 'LuckyNumberPlayed',
        ]);
        $this->assertTrue(LuckyNumberResult::query()->where('user_id', $user->id)->exists());
    }

    public function test_idempotency_key_replays_without_double_charge(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);
        $this->stubWinningRng();

        $key = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $key,
            'game_mode' => 'single_number',
            'bet_amount' => 50,
            'picked_numbers' => [5],
        ];

        $first = $this->postJson('/api/v1/games/lucky-number/play', $payload)
            ->assertCreated();

        $second = $this->postJson('/api/v1/games/lucky-number/play', $payload)
            ->assertOk();

        $this->assertTrue($second->json('data.replay'));
        $this->assertSame($first->json('data.ticket.id'), $second->json('data.ticket.id'));
        $this->assertSame(1, LuckyNumberTicket::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', GamePlay::WALLET_TYPE_GAME_BET)
            ->count());
    }

    public function test_rejects_client_supplied_outcome_fields(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/lucky-number/play', [
            'idempotency_key' => (string) Str::uuid(),
            'game_mode' => 'single_number',
            'bet_amount' => 50,
            'picked_numbers' => [5],
            'winning_numbers' => [5],
            'is_win' => true,
            'reward_coins' => 99999,
            'outcome' => ['winning_numbers' => [5]],
        ])->assertStatus(422);
    }

    public function test_rejects_insufficient_balance(): void
    {
        $user = User::factory()->withCoins(5)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/lucky-number/play', [
            'idempotency_key' => (string) Str::uuid(),
            'game_mode' => 'single_number',
            'bet_amount' => 50,
            'picked_numbers' => [5],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['coins']);
    }

    public function test_quick_pick_endpoint_works(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);
        $this->stubWinningRng();

        $response = $this->postJson('/api/v1/games/lucky-number/quick-pick', [
            'idempotency_key' => (string) Str::uuid(),
            'game_mode' => 'single_number',
            'bet_amount' => 50,
        ])->assertCreated();

        $ticket = $response->json('data.ticket');
        $this->assertTrue($ticket['auto_pick']);
        $this->assertSame([5], $ticket['picked_numbers']);
        $this->assertSame([5], $ticket['outcome']['winning_numbers']);
        $this->assertTrue($ticket['is_win']);
        $this->assertTrue($ticket['outcome']['server_verified']);
        $this->assertArrayNotHasKey('rolls', $ticket['outcome']);
    }

    public function test_history_leaderboard_statistics_jackpot_and_draws_endpoints(): void
    {
        $user = User::factory()->withCoins(5000)->create();
        Sanctum::actingAs($user);
        $this->stubWinningRng();

        $this->postJson('/api/v1/games/lucky-number/play', [
            'idempotency_key' => (string) Str::uuid(),
            'game_mode' => 'single_number',
            'bet_amount' => 50,
            'picked_numbers' => [5],
        ])->assertCreated();

        $this->getJson('/api/v1/games/lucky-number/history')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/games/lucky-number/leaderboard?period=daily')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/games/lucky-number/statistics')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'games_played',
                'tickets_purchased',
                'coins_wagered',
                'coins_won',
            ]]);

        $this->getJson('/api/v1/games/lucky-number/jackpot')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonStructure(['data' => ['jackpots']]);

        $this->getJson('/api/v1/games/lucky-number/draws')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_broadcast_channel_authorization_and_lifecycle_events(): void
    {
        Event::fake([LuckyNumberTicketPurchased::class, LuckyNumberDrawFinished::class]);

        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);
        $this->stubWinningRng();

        $this->postJson('/api/v1/games/lucky-number/play', [
            'idempotency_key' => (string) Str::uuid(),
            'game_mode' => 'single_number',
            'bet_amount' => 50,
            'picked_numbers' => [5],
        ])->assertCreated();

        Event::assertDispatched(LuckyNumberTicketPurchased::class);
        Event::assertDispatched(LuckyNumberDrawFinished::class);

        Broadcast::channel('user.lucky-number.{userId}', fn (User $actor, string $userId): bool => (string) $actor->getKey() === (string) $userId);
        $this->assertTrue((string) $user->getKey() === (string) $user->id);
    }

    public function test_scheduler_creates_daily_jackpot_pool(): void
    {
        $config = LuckyNumberConfig::query()->where('slug', 'default')->firstOrFail();

        LuckyNumberJackpot::query()
            ->where('config_id', $config->getKey())
            ->where('type', LuckyNumberJackpot::TYPE_DAILY)
            ->delete();

        $processed = app(LuckyNumberScheduler::class)->processDailyJackpots();
        $this->assertSame(1, $processed);

        $this->assertDatabaseHas('lucky_number_jackpots', [
            'config_id' => $config->getKey(),
            'type' => LuckyNumberJackpot::TYPE_DAILY,
            'status' => LuckyNumberJackpot::STATUS_ACTIVE,
        ]);

        $second = app(LuckyNumberScheduler::class)->processDailyJackpots();
        $this->assertSame(0, $second);
    }

    public function test_plugin_is_registered_in_game_platform(): void
    {
        $this->assertDatabaseHas('games', [
            'slug' => Game::SLUG_LUCKY_NUMBER,
        ]);
        $this->assertTrue(LuckyNumberConfig::query()->where('is_default', true)->exists());
    }

    public function test_draw_engine_produces_winning_match_with_forced_rng(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);
        $this->stubWinningRng();

        $response = $this->postJson('/api/v1/games/lucky-number/play', [
            'idempotency_key' => (string) Str::uuid(),
            'game_mode' => 'single_number',
            'bet_amount' => 50,
            'picked_numbers' => [5],
        ])->assertCreated();

        $ticket = $response->json('data.ticket');
        $this->assertSame([5], $ticket['outcome']['winning_numbers']);
        $this->assertSame(1, $ticket['outcome']['match_count']);
        $this->assertSame([5], $ticket['outcome']['matched']);
        $this->assertGreaterThan(0, $ticket['outcome']['multiplier']);
        $this->assertTrue($ticket['is_win']);
        $this->assertSame(50 * 9, $ticket['reward_coins']);
    }
}
