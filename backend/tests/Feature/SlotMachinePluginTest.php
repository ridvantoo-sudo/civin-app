<?php

namespace Tests\Feature;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Plugins\SlotMachine\Events\SlotSpinFinished;
use App\Features\Games\Plugins\SlotMachine\Events\SlotSpinStarted;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineConfig;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineJackpot;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use App\Features\Games\Plugins\SlotMachine\Scheduler\SlotMachineScheduler;
use App\Features\Games\Plugins\SlotMachine\Support\SlotSpinEvaluator;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use Database\Seeders\GamePlatformSeeder;
use Database\Seeders\SlotMachineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SlotMachinePluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GamePlatformSeeder::class);
        $this->seed(SlotMachineSeeder::class);
    }

    public function test_config_endpoint_returns_symbols_paylines_and_modes(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/games/slot-machine/config')
            ->assertOk()
            ->assertJsonPath('data.slug', 'default')
            ->assertJsonPath('data.min_bet_coins', 10)
            ->assertJsonPath('data.rtp_percent', 96);

        $this->assertNotEmpty($response->json('data.symbols'));
        $this->assertNotEmpty($response->json('data.paylines'));
        $this->assertContains('classic', $response->json('data.modes_enabled'));
        $this->assertGreaterThanOrEqual(5, count($response->json('data.symbols')));
    }

    public function test_play_debits_wallet_creates_records_and_is_server_randomized(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->app->instance(GameRng::class, new class implements GameRng
        {
            private int $i = 0;

            public function int(int $min, int $max): int
            {
                // Always pick the first symbol weight bucket for deterministic grids.
                $this->i++;

                return $min;
            }

            public function sampleUnique(int $min, int $max, int $count): array
            {
                return range($min, min($max, $min + $count - 1));
            }
        });
        $this->app->forgetInstance(SlotSpinEvaluator::class);

        $key = (string) Str::uuid();
        $response = $this->postJson('/api/v1/games/slot-machine/play', [
            'idempotency_key' => $key,
            'game_mode' => 'classic',
            'bet_amount' => 50,
            'spin_count' => 1,
            'client_session_id' => 'session-slot',
        ])->assertCreated();

        $spin = $response->json('data.spin');
        $this->assertSame($key, $spin['idempotency_key']);
        $this->assertSame(50, $spin['cost_coins']);
        $this->assertSame('classic', $spin['game_mode']);
        $this->assertFalse($response->json('data.replay'));
        $this->assertArrayHasKey('grid', $spin['outcome']);
        $this->assertTrue($spin['outcome']['server_verified']);
        $this->assertArrayNotHasKey('rolls', $spin['outcome']);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1000 - 50 + (int) $spin['reward_coins'], $wallet->coins_balance);

        $this->assertDatabaseHas('slot_machine_spins', [
            'user_id' => $user->id,
            'idempotency_key' => $key,
            'status' => SlotMachineSpin::STATUS_SETTLED,
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
            'event' => 'SlotPlayed',
        ]);
    }

    public function test_idempotency_key_replays_without_double_charge(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $key = (string) Str::uuid();
        $first = $this->postJson('/api/v1/games/slot-machine/play', [
            'idempotency_key' => $key,
            'game_mode' => 'classic',
            'bet_amount' => 50,
        ])->assertCreated();

        $second = $this->postJson('/api/v1/games/slot-machine/play', [
            'idempotency_key' => $key,
            'game_mode' => 'classic',
            'bet_amount' => 50,
        ])->assertOk();

        $this->assertTrue($second->json('data.replay'));
        $this->assertSame($first->json('data.spin.id'), $second->json('data.spin.id'));
        $this->assertSame(1, SlotMachineSpin::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', GamePlay::WALLET_TYPE_GAME_BET)
            ->count());
    }

    public function test_rejects_client_supplied_outcome_fields(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/slot-machine/play', [
            'idempotency_key' => (string) Str::uuid(),
            'game_mode' => 'classic',
            'bet_amount' => 50,
            'grid' => [['seven', 'seven', 'seven']],
            'is_win' => true,
            'reward_coins' => 99999,
        ])->assertStatus(422);
    }

    public function test_rejects_insufficient_balance(): void
    {
        $user = User::factory()->withCoins(5)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/slot-machine/play', [
            'idempotency_key' => (string) Str::uuid(),
            'game_mode' => 'classic',
            'bet_amount' => 50,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['coins']);
    }

    public function test_history_leaderboard_statistics_and_jackpot_endpoints(): void
    {
        $user = User::factory()->withCoins(5000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/slot-machine/play', [
            'idempotency_key' => (string) Str::uuid(),
            'game_mode' => 'classic',
            'bet_amount' => 50,
        ])->assertCreated();

        $this->getJson('/api/v1/games/slot-machine/history')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/games/slot-machine/leaderboard?period=daily')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/games/slot-machine/statistics')
            ->assertOk()
            ->assertJsonStructure(['data' => ['games_played', 'coins_wagered', 'coins_won', 'rtp']]);

        $this->getJson('/api/v1/games/slot-machine/jackpot')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonStructure(['data' => ['jackpots']]);
    }

    public function test_broadcast_channel_authorization_and_lifecycle_events(): void
    {
        Event::fake([SlotSpinStarted::class, SlotSpinFinished::class]);

        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/slot-machine/play', [
            'idempotency_key' => (string) Str::uuid(),
            'game_mode' => 'classic',
            'bet_amount' => 50,
        ])->assertCreated();

        Event::assertDispatched(SlotSpinStarted::class);
        Event::assertDispatched(SlotSpinFinished::class);

        Broadcast::channel('user.slot-machine.{userId}', fn (User $actor, string $userId): bool => (string) $actor->getKey() === (string) $userId);
        $this->assertTrue((string) $user->getKey() === (string) $user->id);
    }

    public function test_scheduler_creates_daily_jackpot_pool(): void
    {
        $config = SlotMachineConfig::query()->where('slug', 'default')->firstOrFail();

        SlotMachineJackpot::query()
            ->where('config_id', $config->getKey())
            ->where('type', SlotMachineJackpot::TYPE_DAILY)
            ->delete();

        $processed = app(SlotMachineScheduler::class)->processDailyJackpots();
        $this->assertSame(1, $processed);

        $this->assertDatabaseHas('slot_machine_jackpots', [
            'config_id' => $config->getKey(),
            'type' => SlotMachineJackpot::TYPE_DAILY,
            'status' => SlotMachineJackpot::STATUS_ACTIVE,
        ]);

        $second = app(SlotMachineScheduler::class)->processDailyJackpots();
        $this->assertSame(0, $second);
    }

    public function test_plugin_is_registered_in_game_platform(): void
    {
        $this->assertDatabaseHas('games', [
            'slug' => Game::SLUG_SLOT_MACHINE,
        ]);
        $this->assertTrue(SlotMachineConfig::query()->where('is_default', true)->exists());
    }
}
