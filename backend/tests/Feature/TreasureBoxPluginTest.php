<?php

namespace Tests\Feature;

use App\Features\Games\Models\GameHistory;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Models\TreasureBoxParticipant;
use App\Features\Games\Models\TreasureBoxWinner;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureCreated;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureFinished;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureJoined;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureOpened;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureRewarded;
use App\Features\Games\Plugins\TreasureBox\Models\TreasureBoxConfig;
use App\Features\Games\Plugins\TreasureBox\Scheduler\TreasureBoxScheduler;
use App\Features\Games\Plugins\TreasureBox\Services\TreasureBoxService;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use Database\Seeders\GamePlatformSeeder;
use Database\Seeders\TreasureBoxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TreasureBoxPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GamePlatformSeeder::class);
        $this->seed(TreasureBoxSeeder::class);
    }

    public function test_config_endpoint_returns_limits_and_allowed_options(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/games/treasure-box/config')
            ->assertOk()
            ->assertJsonPath('data.slug', 'default')
            ->assertJsonPath('data.min_coins_amount', 100)
            ->assertJsonPath('data.max_winners', 100)
            ->assertJsonPath('data.created_today', 0);

        $this->assertContains('coins', $response->json('data.allowed_reward_types'));
        $this->assertContains('instant', $response->json('data.allowed_types'));
        $this->assertContains('followers', $response->json('data.allowed_eligibilities'));
        $this->assertContains('whitelist', $response->json('data.allowed_eligibilities'));
    }

    public function test_create_deducts_coins_and_stores_treasure(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $key = (string) Str::uuid();
        $response = $this->createTreasure($key, ['total_amount' => 300, 'winner_count' => 3])
            ->assertCreated()
            ->assertJsonPath('data.replay', false)
            ->assertJsonPath('data.treasure.total_amount', 300)
            ->assertJsonPath('data.treasure.remaining_amount', 300)
            ->assertJsonPath('data.treasure.winner_count', 3)
            ->assertJsonPath('data.treasure.status', TreasureBox::STATUS_COUNTDOWN)
            ->assertJsonPath('data.treasure.is_open', true);

        $treasure = TreasureBox::query()->findOrFail($response->json('data.treasure.id'));

        $this->assertSame($user->id, $treasure->creator_id);
        $this->assertSame(300, $treasure->total_amount);
        $this->assertSame(3, $treasure->winner_count);
        $this->assertSame(TreasureBox::REWARD_COINS, $treasure->reward_type);
        $this->assertSame(TreasureBox::SCOPE_EVERYONE, $treasure->eligibility_scope);
        $this->assertSame($key, $treasure->idempotency_key);
        $this->assertNotNull($treasure->game_play_id);
        $this->assertNotNull($treasure->wallet_transaction_id);
        $this->assertNotNull($treasure->config_id);
        $this->assertNotNull($treasure->countdown_started_at);
        $this->assertNotNull($treasure->draw_at);

        $this->assertSame(700, Wallet::query()->where('user_id', $user->id)->firstOrFail()->coins_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => GamePlay::WALLET_TYPE_GAME_BET,
            'amount' => -300,
            'currency' => WalletTransaction::CURRENCY_COINS,
        ]);
        $this->assertDatabaseHas('treasure_box_rewards', [
            'treasure_box_id' => $treasure->id,
            'reward_type' => TreasureBox::REWARD_COINS,
            'amount' => 300,
        ]);
        $this->assertDatabaseHas('game_analytics_events', [
            'user_id' => $user->id,
            'event' => 'treasure_created',
        ]);
    }

    public function test_join_adds_participant(): void
    {
        [, $treasureId] = $this->seedTreasure(['total_amount' => 300, 'winner_count' => 3]);

        $joiner = User::factory()->withCoins(50)->create();
        Sanctum::actingAs($joiner);

        $response = $this->postJson('/api/v1/games/treasure-box/join', [
            'idempotency_key' => (string) Str::uuid(),
            'treasure_box_id' => $treasureId,
        ])->assertCreated();

        $this->assertSame($joiner->id, $response->json('data.participant.user_id'));
        $this->assertSame(1, $response->json('data.treasure.participant_count'));
        $this->assertDatabaseHas('treasure_box_participants', [
            'treasure_box_id' => $treasureId,
            'user_id' => $joiner->id,
        ]);
        $this->assertDatabaseHas('game_analytics_events', [
            'user_id' => $joiner->id,
            'event' => 'treasure_joined',
        ]);
    }

    public function test_draw_selects_winners_and_credits_wallet(): void
    {
        [, $treasureId] = $this->seedTreasure([
            'total_amount' => 300,
            'winner_count' => 2,
            'distribution_type' => TreasureBox::DIST_FIXED,
            'countdown_seconds' => 5,
        ]);

        $joiners = [];
        foreach (range(1, 3) as $ignored) {
            $joiner = User::factory()->withCoins(0)->create();
            Sanctum::actingAs($joiner);
            $this->postJson('/api/v1/games/treasure-box/join', [
                'idempotency_key' => (string) Str::uuid(),
                'treasure_box_id' => $treasureId,
            ])->assertCreated();
            $joiners[] = $joiner;
        }

        TreasureBox::query()->whereKey($treasureId)->update(['draw_at' => now()->subSecond()]);
        app(TreasureBoxService::class)->drawWinners($treasureId);

        $treasure = TreasureBox::query()->findOrFail($treasureId);
        $this->assertSame(TreasureBox::STATUS_FINISHED, $treasure->status);
        $this->assertSame(0, $treasure->remaining_amount);

        $winners = TreasureBoxWinner::query()->where('treasure_box_id', $treasureId)->get();
        $this->assertCount(2, $winners);
        $this->assertSame(300, (int) $winners->sum('reward_amount'));

        foreach ($winners as $winner) {
            $this->assertSame(
                $winner->reward_amount,
                Wallet::query()->where('user_id', $winner->user_id)->firstOrFail()->coins_balance,
            );
            $this->assertDatabaseHas('game_rewards', [
                'user_id' => $winner->user_id,
                'coins' => $winner->reward_amount,
            ]);
        }

        $this->assertDatabaseHas('game_analytics_events', ['event' => 'treasure_rewarded']);
        $this->assertDatabaseHas('game_analytics_events', ['event' => 'treasure_finished']);
    }

    public function test_create_is_idempotent(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $key = (string) Str::uuid();
        $first = $this->createTreasure($key, ['total_amount' => 200])->assertCreated();
        $second = $this->createTreasure($key, ['total_amount' => 200])->assertOk();

        $this->assertTrue($second->json('data.replay'));
        $this->assertSame($first->json('data.treasure.id'), $second->json('data.treasure.id'));
        $this->assertSame(1, TreasureBox::query()->where('creator_id', $user->id)->count());
        $this->assertSame(800, Wallet::query()->where('user_id', $user->id)->firstOrFail()->coins_balance);
        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', GamePlay::WALLET_TYPE_GAME_BET)
            ->count());
    }

    public function test_join_is_idempotent(): void
    {
        [, $treasureId] = $this->seedTreasure(['total_amount' => 300, 'winner_count' => 3]);

        $joiner = User::factory()->withCoins(0)->create();
        Sanctum::actingAs($joiner);

        $key = (string) Str::uuid();
        $first = $this->postJson('/api/v1/games/treasure-box/join', [
            'idempotency_key' => $key,
            'treasure_box_id' => $treasureId,
        ])->assertCreated();

        $second = $this->postJson('/api/v1/games/treasure-box/join', [
            'idempotency_key' => $key,
            'treasure_box_id' => $treasureId,
        ])->assertOk();

        $this->assertTrue($second->json('data.replay'));
        $this->assertSame($first->json('data.participant.id'), $second->json('data.participant.id'));
        $this->assertSame(1, TreasureBoxParticipant::query()->where('user_id', $joiner->id)->count());
    }

    public function test_duplicate_join_with_new_key_is_rejected(): void
    {
        [, $treasureId] = $this->seedTreasure(['total_amount' => 300, 'winner_count' => 3]);

        $joiner = User::factory()->withCoins(0)->create();
        Sanctum::actingAs($joiner);

        $this->postJson('/api/v1/games/treasure-box/join', [
            'idempotency_key' => (string) Str::uuid(),
            'treasure_box_id' => $treasureId,
        ])->assertCreated();

        $this->postJson('/api/v1/games/treasure-box/join', [
            'idempotency_key' => (string) Str::uuid(),
            'treasure_box_id' => $treasureId,
        ])->assertStatus(422)->assertJsonValidationErrors(['treasure_box_id']);

        $this->assertSame(1, TreasureBoxParticipant::query()->where('user_id', $joiner->id)->count());
    }

    public function test_whitelist_scope_gates_joins(): void
    {
        $invited = User::factory()->withCoins(0)->create();

        [, $treasureId] = $this->seedTreasure([
            'total_amount' => 300,
            'winner_count' => 3,
            'eligibility_scope' => TreasureBox::SCOPE_WHITELIST,
            'whitelist_user_ids' => [$invited->id],
        ]);

        Sanctum::actingAs(User::factory()->withCoins(0)->create());
        $this->postJson('/api/v1/games/treasure-box/join', [
            'idempotency_key' => (string) Str::uuid(),
            'treasure_box_id' => $treasureId,
        ])->assertStatus(422);

        Sanctum::actingAs($invited);
        $this->postJson('/api/v1/games/treasure-box/join', [
            'idempotency_key' => (string) Str::uuid(),
            'treasure_box_id' => $treasureId,
        ])->assertCreated();
    }

    public function test_expire_refunds_creator(): void
    {
        [$creator, $treasureId] = $this->seedTreasure(['total_amount' => 300, 'winner_count' => 3]);

        TreasureBox::query()->whereKey($treasureId)->update(['expires_at' => now()->subMinute()]);
        $balanceBefore = Wallet::query()->where('user_id', $creator->id)->firstOrFail()->coins_balance;

        Sanctum::actingAs(User::factory()->withCoins(0)->create());
        $this->postJson('/api/v1/games/treasure-box/join', [
            'idempotency_key' => (string) Str::uuid(),
            'treasure_box_id' => $treasureId,
        ])->assertStatus(422)->assertJsonValidationErrors(['treasure_box_id']);

        $treasure = TreasureBox::query()->findOrFail($treasureId);
        $this->assertSame(TreasureBox::STATUS_EXPIRED, $treasure->status);
        $this->assertSame(0, $treasure->remaining_amount);

        $this->assertSame(
            $balanceBefore + 300,
            Wallet::query()->where('user_id', $creator->id)->firstOrFail()->coins_balance,
        );
        $this->assertDatabaseHas('game_analytics_events', ['event' => 'treasure_expired']);
    }

    public function test_insufficient_balance_is_rejected(): void
    {
        $user = User::factory()->withCoins(100)->create();
        Sanctum::actingAs($user);

        $this->createTreasure((string) Str::uuid(), ['total_amount' => 500])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['coins']);

        $this->assertSame(0, TreasureBox::query()->count());
        $this->assertSame(100, Wallet::query()->where('user_id', $user->id)->firstOrFail()->coins_balance);
    }

    public function test_guests_cannot_create_treasures(): void
    {
        $guest = User::factory()->state(['is_guest' => true])->withCoins(1000)->create();
        Sanctum::actingAs($guest);

        $this->createTreasure((string) Str::uuid(), ['total_amount' => 200])->assertForbidden();
    }

    public function test_statistics_history_and_leaderboard_endpoints(): void
    {
        [$creator, $treasureId] = $this->seedTreasure([
            'total_amount' => 300,
            'winner_count' => 1,
            'distribution_type' => TreasureBox::DIST_FIXED,
            'countdown_seconds' => 5,
        ]);

        $joiner = User::factory()->withCoins(0)->create();
        Sanctum::actingAs($joiner);
        $this->postJson('/api/v1/games/treasure-box/join', [
            'idempotency_key' => (string) Str::uuid(),
            'treasure_box_id' => $treasureId,
        ])->assertCreated();

        TreasureBox::query()->whereKey($treasureId)->update(['draw_at' => now()->subSecond()]);
        app(TreasureBoxService::class)->drawWinners($treasureId);

        $joined = $this->getJson('/api/v1/games/treasure-box/history?type=joined')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'total_amount', 'remaining_amount']]]);
        $this->assertSame($treasureId, $joined->json('data.0.id'));

        Sanctum::actingAs($creator);
        $created = $this->getJson('/api/v1/games/treasure-box/history?type=created')->assertOk();
        $this->assertSame($treasureId, $created->json('data.0.id'));

        $this->getJson('/api/v1/games/treasure-box/history?type=all')->assertOk();
        $this->getJson('/api/v1/games/treasure-box/leaderboard?period=daily&type=hosts')->assertOk();
        $this->getJson('/api/v1/games/treasure-box/leaderboard?period=monthly&type=winners')->assertOk();
        $this->getJson('/api/v1/games/treasure-box/leaderboard?period=daily&type=hunters')->assertOk();

        $stats = $this->getJson('/api/v1/games/treasure-box/statistics')->assertOk();
        $this->assertSame(1, $stats->json('data.treasures_created'));
        $this->assertSame(300, $stats->json('data.total_spent_amount'));

        Sanctum::actingAs($joiner);
        $joinerStats = $this->getJson('/api/v1/games/treasure-box/statistics')->assertOk();
        $this->assertSame(1, $joinerStats->json('data.treasures_joined'));
        $this->assertSame(1, $joinerStats->json('data.treasures_won'));
        $this->assertSame(300, $joinerStats->json('data.total_reward_amount'));

        $this->assertGreaterThanOrEqual(2, GameHistory::query()->count());
    }

    public function test_realtime_events_are_dispatched(): void
    {
        Event::fake([
            TreasureCreated::class,
            TreasureOpened::class,
            TreasureJoined::class,
            TreasureRewarded::class,
            TreasureFinished::class,
        ]);

        $creator = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($creator);
        $response = $this->createTreasure((string) Str::uuid(), [
            'total_amount' => 200,
            'winner_count' => 1,
            'countdown_seconds' => 5,
        ])->assertCreated();

        Event::assertDispatched(TreasureCreated::class);
        Event::assertDispatched(TreasureOpened::class);

        $treasureId = $response->json('data.treasure.id');

        Sanctum::actingAs(User::factory()->withCoins(0)->create());
        $this->postJson('/api/v1/games/treasure-box/join', [
            'idempotency_key' => (string) Str::uuid(),
            'treasure_box_id' => $treasureId,
        ])->assertCreated();

        Event::assertDispatched(TreasureJoined::class);

        TreasureBox::query()->whereKey($treasureId)->update(['draw_at' => now()->subSecond()]);
        app(TreasureBoxService::class)->drawWinners($treasureId);

        Event::assertDispatched(TreasureRewarded::class);
        Event::assertDispatched(TreasureFinished::class);
    }

    public function test_channel_auth_for_treasure_box(): void
    {
        $user = User::factory()->withCoins(100)->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-user.treasure-box.'.$user->id,
            'socket_id' => '1234.5678',
        ])->assertOk();

        $authorized = null;
        foreach (Broadcast::getChannels() as $pattern => $callback) {
            if ($pattern === 'user.treasure-box.{userId}') {
                $authorized = (bool) $callback($user, (string) $other->id);
            }
        }
        $this->assertFalse($authorized);

        $created = new TreasureCreated(TreasureBox::query()->make([
            'creator_id' => $user->id,
            'room_id' => (string) Str::uuid(),
            'room_type' => TreasureBox::ROOM_LIVE,
            'reward_type' => TreasureBox::REWARD_COINS,
            'total_amount' => 100,
            'remaining_amount' => 100,
            'winner_count' => 2,
            'participant_count' => 0,
            'status' => TreasureBox::STATUS_COUNTDOWN,
            'type' => TreasureBox::TYPE_INSTANT,
            'visibility' => TreasureBox::VIS_LIVE_ROOM,
            'eligibility_scope' => TreasureBox::SCOPE_EVERYONE,
            'countdown_seconds' => 30,
            'claim_duration_seconds' => 300,
        ]));

        $channels = collect($created->broadcastOn())->map(fn ($channel) => $channel->name)->all();
        $this->assertContains('private-user.games.'.$user->id, $channels);
        $this->assertContains('private-user.treasure-box.'.$user->id, $channels);
        $this->assertSame('treasure.created', $created->broadcastAs());
        $this->assertArrayNotHasKey('whitelist_user_ids', $created->broadcastWith());
    }

    public function test_inactive_config_blocks_creation(): void
    {
        TreasureBoxConfig::query()->update(['status' => TreasureBoxConfig::STATUS_INACTIVE]);

        Sanctum::actingAs(User::factory()->withCoins(1000)->create());
        $this->createTreasure((string) Str::uuid(), ['total_amount' => 200])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['config']);
    }

    public function test_creator_cannot_join_own_treasure(): void
    {
        [$creator, $treasureId] = $this->seedTreasure(['total_amount' => 300, 'winner_count' => 3]);

        Sanctum::actingAs($creator);
        $this->postJson('/api/v1/games/treasure-box/join', [
            'idempotency_key' => (string) Str::uuid(),
            'treasure_box_id' => $treasureId,
        ])->assertStatus(422)->assertJsonValidationErrors(['treasure_box_id']);
    }

    public function test_client_cannot_seed_server_owned_fields(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->createTreasure((string) Str::uuid(), [
            'remaining_amount' => 999999,
            'status' => TreasureBox::STATUS_OPEN,
            'participant_count' => 99,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['remaining_amount', 'status', 'participant_count']);
    }

    public function test_scheduler_creates_auto_treasure_from_admin_rules(): void
    {
        $sponsor = User::factory()->withCoins(100000)->create();
        $config = TreasureBoxConfig::query()->where('slug', 'default')->firstOrFail();
        $config->forceFill([
            'auto_rules' => [[
                'id' => 'hourly-public',
                'enabled' => true,
                'creator_user_id' => $sponsor->getKey(),
                'interval_minutes' => 60,
                'reward_type' => TreasureBox::REWARD_COINS,
                'total_amount' => 500,
                'winner_count' => 2,
                'countdown_seconds' => 15,
                'claim_duration_seconds' => 120,
                'eligibility_scope' => TreasureBox::SCOPE_EVERYONE,
                'visibility' => TreasureBox::VIS_PUBLIC,
                'auto_start' => true,
                'message' => 'Auto drop',
            ]],
        ])->save();

        $result = app(TreasureBoxScheduler::class)->processDue();

        $this->assertSame(1, $result['auto_created']);
        $this->assertDatabaseHas('treasure_boxes', [
            'creator_id' => $sponsor->getKey(),
            'type' => TreasureBox::TYPE_AUTO,
            'total_amount' => 500,
        ]);

        $second = app(TreasureBoxScheduler::class)->processDue();
        $this->assertSame(0, $second['auto_created']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function createTreasure(string $idempotencyKey, array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/games/treasure-box/create', array_merge([
            'idempotency_key' => $idempotencyKey,
            'type' => TreasureBox::TYPE_INSTANT,
            'reward_type' => TreasureBox::REWARD_COINS,
            'distribution_type' => TreasureBox::DIST_RANDOM,
            'visibility' => TreasureBox::VIS_PUBLIC,
            'eligibility_scope' => TreasureBox::SCOPE_EVERYONE,
            'total_amount' => 300,
            'winner_count' => 3,
            'countdown_seconds' => 30,
            'claim_duration_seconds' => 300,
            'auto_start' => true,
            'message' => 'Good luck',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: string}
     */
    private function seedTreasure(array $overrides = []): array
    {
        $creator = User::factory()->withCoins(100000)->create();
        Sanctum::actingAs($creator);

        $response = $this->createTreasure((string) Str::uuid(), $overrides)->assertCreated();

        return [$creator, (string) $response->json('data.treasure.id')];
    }
}
