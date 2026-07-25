<?php

namespace Tests\Feature;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameHistory;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelFinished;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelStarted;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelConfig;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSector;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use Database\Seeders\GamePlatformSeeder;
use Database\Seeders\LuckyWheelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LuckyWheelPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GamePlatformSeeder::class);
        $this->seed(LuckyWheelSeeder::class);
    }

    public function test_config_endpoint_returns_active_sectors(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/games/lucky-wheel/config')
            ->assertOk()
            ->assertJsonPath('data.slug', 'default')
            ->assertJsonPath('data.spin_cost_coins', 100);

        $this->assertNotEmpty($response->json('data.sectors'));
        $this->assertGreaterThanOrEqual(5, count($response->json('data.sectors')));
    }

    public function test_spin_debits_wallet_creates_records_and_is_server_randomized(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->app->instance(GameRng::class, new class implements GameRng
        {
            public function int(int $min, int $max): int
            {
                return 31; // lands on second sector (weight 30 then 25) => 50 coins
            }

            public function sampleUnique(int $min, int $max, int $count): array
            {
                return range($min, min($max, $min + $count - 1));
            }
        });

        $key = (string) Str::uuid();
        $response = $this->postJson('/api/v1/games/lucky-wheel/spins', [
            'idempotency_key' => $key,
            'client_session_id' => 'session-abc',
        ])->assertCreated();

        $spin = $response->json('data.spin');
        $this->assertSame($key, $spin['idempotency_key']);
        $this->assertSame(100, $spin['cost_coins']);
        $this->assertSame('coins', $spin['reward_type']);
        $this->assertSame(50, $spin['reward_amount']);
        $this->assertFalse($response->json('data.replay'));
        $this->assertArrayNotHasKey('roll', $spin['outcome'] ?? []);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(950, $wallet->coins_balance);

        $this->assertDatabaseHas('lucky_wheel_spins', [
            'user_id' => $user->id,
            'idempotency_key' => $key,
            'status' => LuckyWheelSpin::STATUS_SETTLED,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => GamePlay::WALLET_TYPE_GAME_BET,
            'amount' => -100,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
            'amount' => 50,
            'currency' => WalletTransaction::CURRENCY_COINS,
        ]);
        $this->assertDatabaseHas('game_plays', [
            'user_id' => $user->id,
            'bet_coins' => 100,
            'reward_coins' => 50,
        ]);
        $this->assertDatabaseHas('game_rewards', [
            'user_id' => $user->id,
            'coins' => 50,
            'status' => GameReward::STATUS_GRANTED,
        ]);
        $this->assertDatabaseHas('game_histories', [
            'user_id' => $user->id,
            'bet_coins' => 100,
        ]);
        $this->assertDatabaseHas('game_analytics_events', [
            'user_id' => $user->id,
            'event' => 'lucky_wheel_played',
        ]);
    }

    public function test_idempotency_key_replays_without_double_charge(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $key = (string) Str::uuid();
        $first = $this->postJson('/api/v1/games/lucky-wheel/spins', [
            'idempotency_key' => $key,
        ])->assertCreated();

        $second = $this->postJson('/api/v1/games/lucky-wheel/spins', [
            'idempotency_key' => $key,
        ])->assertOk();

        $this->assertTrue($second->json('data.replay'));
        $this->assertSame($first->json('data.spin.id'), $second->json('data.spin.id'));
        $this->assertSame(1, LuckyWheelSpin::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', GamePlay::WALLET_TYPE_GAME_BET)
            ->count());
    }

    public function test_rejects_client_supplied_outcome_fields(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/lucky-wheel/spins', [
            'idempotency_key' => (string) Str::uuid(),
            'reward_amount' => 99999,
            'sector_index' => 6,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['reward_amount', 'sector_index']);
    }

    public function test_insufficient_coins_rejected(): void
    {
        $user = User::factory()->withCoins(10)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/lucky-wheel/spins', [
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['coins']);
    }

    public function test_non_wallet_reward_records_fulfillment_pending(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $config = LuckyWheelConfig::query()->where('slug', 'default')->firstOrFail();
        LuckyWheelSector::query()->where('config_id', $config->id)->update(['weight' => 0, 'status' => LuckyWheelSector::STATUS_INACTIVE]);
        LuckyWheelSector::query()->create([
            'config_id' => $config->id,
            'position' => 10,
            'label' => 'VIP Pass',
            'reward_type' => LuckyWheelSector::REWARD_TYPE_VIP,
            'reward_amount' => 1,
            'reward_reference' => 'vip_7d',
            'weight' => 1,
            'is_jackpot' => false,
            'requires_fulfillment' => true,
            'status' => LuckyWheelSector::STATUS_ACTIVE,
        ]);

        $response = $this->postJson('/api/v1/games/lucky-wheel/spins', [
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        $this->assertSame(LuckyWheelSpin::STATUS_FULFILLMENT_PENDING, $response->json('data.spin.status'));
        $this->assertSame('vip', $response->json('data.spin.reward_type'));
        $this->assertDatabaseHas('game_rewards', [
            'user_id' => $user->id,
            'status' => GameReward::STATUS_PENDING_FULFILLMENT,
        ]);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(900, $wallet->coins_balance);
    }

    public function test_history_and_leaderboard_endpoints(): void
    {
        $user = User::factory()->withCoins(5000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/lucky-wheel/spins', [
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        $this->getJson('/api/v1/games/lucky-wheel/history')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'idempotency_key', 'cost_coins']]]);

        $this->getJson('/api/v1/games/lucky-wheel/leaderboard?period=daily')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/games/lucky-wheel/leaderboard?period=monthly')
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, GameHistory::query()->where('user_id', $user->id)->count());
    }

    public function test_realtime_events_are_dispatched(): void
    {
        Event::fake([LuckyWheelStarted::class, LuckyWheelFinished::class]);

        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/lucky-wheel/spins', [
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        Event::assertDispatched(LuckyWheelStarted::class);
        Event::assertDispatched(LuckyWheelFinished::class);
    }

    public function test_generic_play_endpoint_still_works_for_lucky_wheel(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_LUCKY_WHEEL,
            'bet_coins' => 100,
        ])->assertCreated()
            ->assertJsonPath('data.play.bet_coins', 100);
    }

    public function test_channel_auth_for_lucky_wheel(): void
    {
        $user = User::factory()->withCoins(100)->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-user.lucky-wheel.'.$user->id,
            'socket_id' => '1234.5678',
        ])->assertOk();

        // Channel callback rejects foreign user ids (asserted directly for deterministic tests).
        $authorized = false;
        foreach (Broadcast::getChannels() as $pattern => $callback) {
            if ($pattern !== 'user.lucky-wheel.{userId}') {
                continue;
            }
            $authorized = (bool) $callback($user, (string) $other->id);
        }
        $this->assertFalse($authorized);

        $started = new LuckyWheelStarted(
            LuckyWheelSpin::query()->make([
                'user_id' => $user->id,
                'idempotency_key' => (string) Str::uuid(),
                'status' => LuckyWheelSpin::STATUS_SETTLED,
                'cost_coins' => 100,
            ]),
        );
        $channels = collect($started->broadcastOn())->map(fn ($ch) => $ch->name)->all();
        $this->assertContains('private-user.games.'.$user->id, $channels);
        $this->assertContains('private-user.lucky-wheel.'.$user->id, $channels);
    }
}
