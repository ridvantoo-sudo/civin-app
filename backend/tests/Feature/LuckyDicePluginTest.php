<?php

namespace Tests\Feature;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameHistory;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceFinished;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceStarted;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceConfig;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use Database\Seeders\GamePlatformSeeder;
use Database\Seeders\LuckyDiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LuckyDicePluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GamePlatformSeeder::class);
        $this->seed(LuckyDiceSeeder::class);
    }

    public function test_config_endpoint_returns_modes_and_multipliers(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/games/lucky-dice/config')
            ->assertOk()
            ->assertJsonPath('data.slug', 'default')
            ->assertJsonPath('data.min_bet_coins', 50)
            ->assertJsonPath('data.max_bet_coins', 10000)
            ->assertJsonPath('data.multiplier_big_small', 2)
            ->assertJsonPath('data.multiplier_odd_even', 2)
            ->assertJsonPath('data.multiplier_triple', 30)
            ->assertJsonPath('data.jackpot_coins', 10000);

        $this->assertContains('big_small', $response->json('data.modes_enabled'));
        $this->assertNotEmpty($response->json('data.exact_total_multipliers'));
        $this->assertNotEmpty($response->json('data.chip_amounts'));
    }

    public function test_play_debits_wallet_stores_dice_and_settles(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        // 1,2,3 => total 6 (small, even) — big loses
        $this->mockDice(1, 2, 3);

        $key = (string) Str::uuid();
        $response = $this->postJson('/api/v1/games/lucky-dice/play', [
            'idempotency_key' => $key,
            'bet_mode' => 'big_small',
            'prediction' => 'big',
            'bet_amount' => 100,
            'client_session_id' => 'session-dice',
        ])->assertCreated();

        $bet = $response->json('data.bet');
        $this->assertSame($key, $bet['idempotency_key']);
        $this->assertSame(100, $bet['bet_amount']);
        $this->assertFalse($bet['is_win']);
        $this->assertSame(LuckyDiceBet::STATUS_SETTLED, $bet['status']);
        $this->assertSame(1, $bet['result']['dice_1']);
        $this->assertSame(2, $bet['result']['dice_2']);
        $this->assertSame(3, $bet['result']['dice_3']);
        $this->assertSame(6, $bet['result']['total']);
        $this->assertFalse($response->json('data.replay'));

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(900, $wallet->coins_balance);

        $this->assertDatabaseHas('lucky_dice_bets', [
            'user_id' => $user->id,
            'idempotency_key' => $key,
            'status' => LuckyDiceBet::STATUS_SETTLED,
        ]);
        $this->assertDatabaseHas('lucky_dice_results', [
            'user_id' => $user->id,
            'dice_1' => 1,
            'dice_2' => 2,
            'dice_3' => 3,
            'total' => 6,
            'result' => 'lost',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => GamePlay::WALLET_TYPE_GAME_BET,
            'amount' => -100,
        ]);
        $this->assertDatabaseHas('game_plays', [
            'user_id' => $user->id,
            'bet_coins' => 100,
            'reward_coins' => 0,
        ]);
        $this->assertDatabaseHas('game_rewards', [
            'user_id' => $user->id,
            'coins' => 0,
            'status' => GameReward::STATUS_GRANTED,
        ]);
        $this->assertDatabaseHas('game_histories', [
            'user_id' => $user->id,
            'bet_coins' => 100,
        ]);
        $this->assertDatabaseHas('game_analytics_events', [
            'user_id' => $user->id,
            'event' => 'lucky_dice_played',
        ]);
    }

    public function test_idempotency_key_replays_without_double_charge(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);
        $this->mockDice(4, 5, 6);

        $key = (string) Str::uuid();
        $first = $this->postJson('/api/v1/games/lucky-dice/play', [
            'idempotency_key' => $key,
            'bet_mode' => 'big_small',
            'prediction' => 'big',
            'bet_amount' => 100,
        ])->assertCreated();

        $second = $this->postJson('/api/v1/games/lucky-dice/play', [
            'idempotency_key' => $key,
            'bet_mode' => 'big_small',
            'prediction' => 'big',
            'bet_amount' => 100,
        ])->assertOk();

        $this->assertTrue($second->json('data.replay'));
        $this->assertSame($first->json('data.bet.id'), $second->json('data.bet.id'));
        $this->assertSame(1, LuckyDiceBet::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', GamePlay::WALLET_TYPE_GAME_BET)
            ->count());
    }

    public function test_rejects_client_supplied_dice_fields(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/lucky-dice/play', [
            'idempotency_key' => (string) Str::uuid(),
            'bet_mode' => 'big_small',
            'prediction' => 'big',
            'bet_amount' => 100,
            'dice_1' => 6,
            'dice_2' => 6,
            'dice_3' => 6,
            'reward_amount' => 99999,
            'total' => 18,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['dice_1', 'dice_2', 'dice_3', 'reward_amount', 'total']);
    }

    public function test_insufficient_coins_rejected(): void
    {
        $user = User::factory()->withCoins(10)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/lucky-dice/play', [
            'idempotency_key' => (string) Str::uuid(),
            'bet_mode' => 'big_small',
            'prediction' => 'big',
            'bet_amount' => 100,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['coins']);
    }

    public function test_big_small_win_path_with_mocked_rng(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        // 5+5+5 = 15 => big
        $this->mockDice(5, 5, 5);

        $response = $this->postJson('/api/v1/games/lucky-dice/play', [
            'idempotency_key' => (string) Str::uuid(),
            'bet_mode' => 'big_small',
            'prediction' => 'big',
            'bet_amount' => 100,
        ])->assertCreated();

        $bet = $response->json('data.bet');
        $this->assertTrue($bet['is_win']);
        $this->assertTrue($bet['is_jackpot']);
        $this->assertSame(10000, $bet['reward_amount']); // jackpot on triple
        $this->assertSame('jackpot', $bet['reward_type']);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        // 1000 - 100 + 10000
        $this->assertSame(10900, $wallet->coins_balance);
    }

    public function test_triple_match_win_without_jackpot_when_mode_pays_triple_multiplier(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        // Disable jackpot for this scenario to assert pure triple multiplier
        LuckyDiceConfig::query()
            ->where('slug', 'default')
            ->update(['jackpot_enabled' => false]);

        $this->mockDice(4, 4, 4);

        $response = $this->postJson('/api/v1/games/lucky-dice/play', [
            'idempotency_key' => (string) Str::uuid(),
            'bet_mode' => 'triple_match',
            'prediction' => 'any',
            'bet_amount' => 100,
        ])->assertCreated();

        $bet = $response->json('data.bet');
        $this->assertTrue($bet['is_win']);
        $this->assertFalse($bet['is_jackpot']);
        $this->assertSame(3000, $bet['reward_amount']); // 100 * 30
        $this->assertTrue($bet['result']['is_triple']);
    }

    public function test_history_and_leaderboard_endpoints(): void
    {
        $user = User::factory()->withCoins(5000)->create();
        Sanctum::actingAs($user);
        $this->mockDice(1, 1, 1);

        $this->postJson('/api/v1/games/lucky-dice/play', [
            'idempotency_key' => (string) Str::uuid(),
            'bet_mode' => 'big_small',
            'prediction' => 'small',
            'bet_amount' => 100,
        ])->assertCreated();

        $this->getJson('/api/v1/games/lucky-dice/history')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'idempotency_key', 'bet_amount', 'result']]]);

        $this->getJson('/api/v1/games/lucky-dice/leaderboard?period=daily')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/games/lucky-dice/leaderboard?period=monthly&type=profit')
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, GameHistory::query()->where('user_id', $user->id)->count());
    }

    public function test_realtime_events_are_dispatched(): void
    {
        Event::fake([LuckyDiceStarted::class, LuckyDiceFinished::class]);

        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);
        $this->mockDice(2, 3, 4);

        $this->postJson('/api/v1/games/lucky-dice/play', [
            'idempotency_key' => (string) Str::uuid(),
            'bet_mode' => 'odd_even',
            'prediction' => 'odd',
            'bet_amount' => 50,
        ])->assertCreated();

        Event::assertDispatched(LuckyDiceStarted::class);
        Event::assertDispatched(LuckyDiceFinished::class);
    }

    public function test_generic_play_endpoint_still_works_for_lucky_dice(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_LUCKY_DICE,
            'bet_coins' => 100,
            'input' => ['guess' => 3],
        ])->assertCreated()
            ->assertJsonPath('data.play.bet_coins', 100);
    }

    public function test_channel_auth_for_lucky_dice(): void
    {
        $user = User::factory()->withCoins(100)->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-user.lucky-dice.'.$user->id,
            'socket_id' => '1234.5678',
        ])->assertOk();

        $authorized = false;
        foreach (Broadcast::getChannels() as $pattern => $callback) {
            if ($pattern !== 'user.lucky-dice.{userId}') {
                continue;
            }
            $authorized = (bool) $callback($user, (string) $other->id);
        }
        $this->assertFalse($authorized);

        $started = new LuckyDiceStarted(
            LuckyDiceBet::query()->make([
                'user_id' => $user->id,
                'idempotency_key' => (string) Str::uuid(),
                'status' => LuckyDiceBet::STATUS_SETTLED,
                'bet_amount' => 100,
                'bet_mode' => LuckyDiceBet::MODE_BIG_SMALL,
                'prediction' => 'big',
            ]),
        );
        $channels = collect($started->broadcastOn())->map(fn ($ch) => $ch->name)->all();
        $this->assertContains('private-user.games.'.$user->id, $channels);
        $this->assertContains('private-user.lucky-dice.'.$user->id, $channels);
    }

    private function mockDice(int $d1, int $d2, int $d3): void
    {
        $queue = [$d1, $d2, $d3];

        $this->app->instance(GameRng::class, new class($queue) implements GameRng
        {
            /** @param  list<int>  $queue */
            public function __construct(private array $queue) {}

            public function int(int $min, int $max): int
            {
                if ($this->queue === []) {
                    return $min;
                }

                return array_shift($this->queue);
            }

            public function sampleUnique(int $min, int $max, int $count): array
            {
                return range($min, min($max, $min + $count - 1));
            }
        });
    }
}
