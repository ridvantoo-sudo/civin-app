<?php

namespace Tests\Feature;

use App\Features\Followers\Models\Follow;
use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameHistory;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Models\RedEnvelopeClaim;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeClaimed;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeCreated;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeFinished;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeOpened;
use App\Features\Games\Plugins\RedEnvelope\Models\RedEnvelopeConfig;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use Database\Seeders\GamePlatformSeeder;
use Database\Seeders\RedEnvelopeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RedEnvelopePluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GamePlatformSeeder::class);
        $this->seed(RedEnvelopeSeeder::class);
    }

    public function test_config_endpoint_returns_limits_and_allowed_options(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/games/red-envelope/config')
            ->assertOk()
            ->assertJsonPath('data.slug', 'default')
            ->assertJsonPath('data.min_coins_amount', 100)
            ->assertJsonPath('data.max_winners', 100)
            ->assertJsonPath('data.created_today', 0);

        $this->assertContains('coins', $response->json('data.allowed_reward_types'));
        $this->assertContains('diamonds', $response->json('data.allowed_reward_types'));
        $this->assertContains('random', $response->json('data.allowed_distribution_types'));
        $this->assertContains('followers', $response->json('data.allowed_claim_scopes'));
    }

    public function test_create_deducts_coins_and_stores_plugin_and_legacy_columns(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $key = (string) Str::uuid();
        $response = $this->createEnvelope($key, ['total_amount' => 300, 'winner_count' => 3])
            ->assertCreated()
            ->assertJsonPath('data.replay', false)
            ->assertJsonPath('data.envelope.total_amount', 300)
            ->assertJsonPath('data.envelope.remaining_amount', 300)
            ->assertJsonPath('data.envelope.winner_count', 3)
            ->assertJsonPath('data.envelope.status', RedEnvelope::STATUS_OPEN)
            ->assertJsonPath('data.envelope.is_open', true);

        $envelope = RedEnvelope::query()->findOrFail($response->json('data.envelope.id'));

        // Plugin columns.
        $this->assertSame($user->id, $envelope->creator_id);
        $this->assertSame(300, $envelope->total_amount);
        $this->assertSame(3, $envelope->winner_count);
        $this->assertSame(RedEnvelope::REWARD_COINS, $envelope->reward_type);
        $this->assertSame(RedEnvelope::DIST_RANDOM, $envelope->distribution_type);
        $this->assertSame(RedEnvelope::SCOPE_EVERYONE, $envelope->claim_scope);
        $this->assertSame($key, $envelope->idempotency_key);
        $this->assertNotNull($envelope->game_play_id);
        $this->assertNotNull($envelope->wallet_transaction_id);
        $this->assertNotNull($envelope->config_id);

        // Legacy columns stay mirrored for the /games/play flow.
        $this->assertSame($user->id, $envelope->sender_id);
        $this->assertSame(300, $envelope->total_coins);
        $this->assertSame(300, $envelope->remaining_coins);
        $this->assertSame(3, $envelope->total_slots);
        $this->assertSame(3, $envelope->remaining_slots);

        $this->assertSame(700, Wallet::query()->where('user_id', $user->id)->firstOrFail()->coins_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => GamePlay::WALLET_TYPE_GAME_BET,
            'amount' => -300,
            'currency' => WalletTransaction::CURRENCY_COINS,
        ]);
        $this->assertDatabaseHas('game_analytics_events', [
            'user_id' => $user->id,
            'event' => 'red_envelope_created',
        ]);
    }

    public function test_create_deducts_diamonds_for_diamond_envelopes(): void
    {
        $user = User::factory()->withDiamonds(2000)->create();
        Sanctum::actingAs($user);

        $response = $this->createEnvelope((string) Str::uuid(), [
            'reward_type' => RedEnvelope::REWARD_DIAMONDS,
            'total_amount' => 500,
            'winner_count' => 2,
        ])->assertCreated();

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1500, $wallet->diamonds_balance);
        $this->assertSame(0, $wallet->coins_balance);

        $envelope = RedEnvelope::query()->findOrFail($response->json('data.envelope.id'));
        $this->assertSame(500, $envelope->total_amount);
        // Legacy coin columns stay zeroed so the legacy flow refuses diamonds.
        $this->assertSame(0, $envelope->total_coins);
        $this->assertSame(0, $envelope->remaining_slots);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'amount' => -500,
            'currency' => WalletTransaction::CURRENCY_DIAMONDS,
        ]);
    }

    public function test_random_claim_credits_wallet_and_updates_remaining(): void
    {
        [$creator, $envelopeId] = $this->seedEnvelope(['total_amount' => 300, 'winner_count' => 3]);

        $claimer = User::factory()->withCoins(50)->create();
        Sanctum::actingAs($claimer);

        $response = $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $envelopeId,
        ])->assertCreated();

        $reward = (int) $response->json('data.claim.reward_amount');
        $this->assertGreaterThan(0, $reward);
        $this->assertSame(RedEnvelope::REWARD_COINS, $response->json('data.claim.reward_type'));
        $this->assertSame(300 - $reward, $response->json('data.envelope.remaining_amount'));
        $this->assertSame(2, $response->json('data.envelope.remaining_count'));

        $this->assertSame(50 + $reward, Wallet::query()->where('user_id', $claimer->id)->firstOrFail()->coins_balance);

        $claim = RedEnvelopeClaim::query()->where('envelope_id', $envelopeId)->firstOrFail();
        $this->assertSame($reward, $claim->reward_amount);
        $this->assertSame($reward, $claim->coins);
        $this->assertNotNull($claim->claimed_at);
        $this->assertNotNull($claim->play_id);
        $this->assertNotNull($claim->wallet_transaction_id);

        $this->assertDatabaseHas('game_plays', [
            'user_id' => $claimer->id,
            'result' => GamePlay::RESULT_CLAIMED,
            'reward_coins' => $reward,
        ]);
        $this->assertDatabaseHas('game_rewards', [
            'user_id' => $claimer->id,
            'coins' => $reward,
        ]);
        $this->assertDatabaseHas('game_analytics_events', [
            'user_id' => $claimer->id,
            'event' => 'red_envelope_claimed',
        ]);
        $this->assertNotSame($creator->id, $claimer->id);
    }

    public function test_fixed_distribution_splits_evenly_with_remainder_last(): void
    {
        [, $envelopeId] = $this->seedEnvelope([
            'total_amount' => 300,
            'winner_count' => 3,
            'distribution_type' => RedEnvelope::DIST_FIXED,
        ]);

        $shares = [];
        foreach (range(1, 3) as $ignored) {
            $claimer = User::factory()->withCoins(0)->create();
            Sanctum::actingAs($claimer);

            $shares[] = (int) $this->postJson('/api/v1/games/red-envelope/claim', [
                'idempotency_key' => (string) Str::uuid(),
                'envelope_id' => $envelopeId,
            ])->assertCreated()->json('data.claim.reward_amount');
        }

        $this->assertSame([100, 100, 100], $shares);
        $this->assertSame(300, array_sum($shares));

        $envelope = RedEnvelope::query()->findOrFail($envelopeId);
        $this->assertSame(0, $envelope->remaining_amount);
        $this->assertSame(0, $envelope->remaining_count);
        $this->assertTrue($envelope->isFinished());
    }

    public function test_fixed_distribution_rolls_remainder_to_the_last_claimer(): void
    {
        [, $envelopeId] = $this->seedEnvelope([
            'total_amount' => 100,
            'winner_count' => 3,
            'distribution_type' => RedEnvelope::DIST_FIXED,
        ]);

        $shares = [];
        foreach (range(1, 3) as $ignored) {
            Sanctum::actingAs(User::factory()->withCoins(0)->create());
            $shares[] = (int) $this->postJson('/api/v1/games/red-envelope/claim', [
                'idempotency_key' => (string) Str::uuid(),
                'envelope_id' => $envelopeId,
            ])->assertCreated()->json('data.claim.reward_amount');
        }

        $this->assertSame([33, 33, 34], $shares);
        $this->assertSame(100, array_sum($shares));
    }

    public function test_create_is_idempotent(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $key = (string) Str::uuid();
        $first = $this->createEnvelope($key, ['total_amount' => 200])->assertCreated();
        $second = $this->createEnvelope($key, ['total_amount' => 200])->assertOk();

        $this->assertTrue($second->json('data.replay'));
        $this->assertSame($first->json('data.envelope.id'), $second->json('data.envelope.id'));
        $this->assertSame(1, RedEnvelope::query()->where('sender_id', $user->id)->count());
        $this->assertSame(800, Wallet::query()->where('user_id', $user->id)->firstOrFail()->coins_balance);
        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', GamePlay::WALLET_TYPE_GAME_BET)
            ->count());
    }

    public function test_claim_is_idempotent(): void
    {
        [, $envelopeId] = $this->seedEnvelope(['total_amount' => 300, 'winner_count' => 3]);

        $claimer = User::factory()->withCoins(0)->create();
        Sanctum::actingAs($claimer);

        $key = (string) Str::uuid();
        $first = $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => $key,
            'envelope_id' => $envelopeId,
        ])->assertCreated();

        $second = $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => $key,
            'envelope_id' => $envelopeId,
        ])->assertOk();

        $this->assertTrue($second->json('data.replay'));
        $this->assertSame($first->json('data.claim.id'), $second->json('data.claim.id'));
        $this->assertSame(1, RedEnvelopeClaim::query()->where('user_id', $claimer->id)->count());
        $this->assertSame(
            (int) $first->json('data.claim.reward_amount'),
            Wallet::query()->where('user_id', $claimer->id)->firstOrFail()->coins_balance,
        );
    }

    public function test_duplicate_claim_with_new_key_is_rejected(): void
    {
        [, $envelopeId] = $this->seedEnvelope(['total_amount' => 300, 'winner_count' => 3]);

        $claimer = User::factory()->withCoins(0)->create();
        Sanctum::actingAs($claimer);

        $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $envelopeId,
        ])->assertCreated();

        $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $envelopeId,
        ])->assertStatus(422)->assertJsonValidationErrors(['envelope_id']);

        $this->assertSame(1, RedEnvelopeClaim::query()->where('user_id', $claimer->id)->count());
    }

    public function test_creator_cannot_claim_own_envelope(): void
    {
        [$creator, $envelopeId] = $this->seedEnvelope(['total_amount' => 300, 'winner_count' => 3]);

        Sanctum::actingAs($creator);
        $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $envelopeId,
        ])->assertStatus(422)->assertJsonValidationErrors(['envelope_id']);
    }

    public function test_expired_envelope_is_rejected_and_refunds_creator(): void
    {
        [$creator, $envelopeId] = $this->seedEnvelope(['total_amount' => 300, 'winner_count' => 3]);

        RedEnvelope::query()->whereKey($envelopeId)->update(['expires_at' => now()->subMinute()]);
        $balanceBefore = Wallet::query()->where('user_id', $creator->id)->firstOrFail()->coins_balance;

        Sanctum::actingAs(User::factory()->withCoins(0)->create());
        $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $envelopeId,
        ])->assertStatus(422)->assertJsonValidationErrors(['envelope_id']);

        $envelope = RedEnvelope::query()->findOrFail($envelopeId);
        $this->assertSame(RedEnvelope::STATUS_EXPIRED, $envelope->status);
        $this->assertSame(0, $envelope->remaining_amount);

        // Unclaimed value goes back to the creator rather than being burned.
        $this->assertSame(
            $balanceBefore + 300,
            Wallet::query()->where('user_id', $creator->id)->firstOrFail()->coins_balance,
        );
        $this->assertDatabaseHas('game_analytics_events', ['event' => 'red_envelope_expired']);
    }

    public function test_followers_only_scope_gates_claims(): void
    {
        [$creator, $envelopeId] = $this->seedEnvelope([
            'total_amount' => 300,
            'winner_count' => 3,
            'claim_scope' => RedEnvelope::SCOPE_FOLLOWERS,
        ]);

        $stranger = User::factory()->withCoins(0)->create();
        Sanctum::actingAs($stranger);
        $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $envelopeId,
        ])->assertStatus(422)->assertJsonValidationErrors(['envelope_id', 'claim_scope']);

        $follower = User::factory()->withCoins(0)->create();
        Follow::query()->create([
            'follower_id' => $follower->id,
            'followed_id' => $creator->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($follower);
        $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $envelopeId,
        ])->assertCreated();
    }

    public function test_whitelist_scope_gates_claims(): void
    {
        $invited = User::factory()->withCoins(0)->create();

        [, $envelopeId] = $this->seedEnvelope([
            'total_amount' => 300,
            'winner_count' => 3,
            'claim_scope' => RedEnvelope::SCOPE_WHITELIST,
            'whitelist_user_ids' => [$invited->id],
        ]);

        Sanctum::actingAs(User::factory()->withCoins(0)->create());
        $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $envelopeId,
        ])->assertStatus(422);

        Sanctum::actingAs($invited);
        $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $envelopeId,
        ])->assertCreated();
    }

    public function test_insufficient_balance_is_rejected(): void
    {
        $user = User::factory()->withCoins(100)->create();
        Sanctum::actingAs($user);

        $this->createEnvelope((string) Str::uuid(), ['total_amount' => 500])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['coins']);

        $this->assertSame(0, RedEnvelope::query()->count());
        $this->assertSame(100, Wallet::query()->where('user_id', $user->id)->firstOrFail()->coins_balance);
    }

    public function test_amount_below_winner_count_is_rejected(): void
    {
        $user = User::factory()->withCoins(10000)->create();
        Sanctum::actingAs($user);

        $this->createEnvelope((string) Str::uuid(), ['total_amount' => 100, 'winner_count' => 101])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['winner_count']);
    }

    public function test_client_cannot_seed_server_owned_fields(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($user);

        $this->createEnvelope((string) Str::uuid(), [
            'remaining_amount' => 999999,
            'status' => RedEnvelope::STATUS_OPEN,
            'total_coins' => 999999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['remaining_amount', 'status', 'total_coins']);
    }

    public function test_claims_can_never_over_allocate_the_funded_total(): void
    {
        [, $envelopeId] = $this->seedEnvelope(['total_amount' => 500, 'winner_count' => 5]);

        foreach (range(1, 5) as $ignored) {
            Sanctum::actingAs(User::factory()->withCoins(0)->create());
            $this->postJson('/api/v1/games/red-envelope/claim', [
                'idempotency_key' => (string) Str::uuid(),
                'envelope_id' => $envelopeId,
            ])->assertCreated();
        }

        $claims = RedEnvelopeClaim::query()->where('envelope_id', $envelopeId)->get();
        $this->assertCount(5, $claims);
        $this->assertLessThanOrEqual(500, (int) $claims->sum('reward_amount'));
        foreach ($claims as $claim) {
            $this->assertGreaterThanOrEqual(1, $claim->reward_amount);
        }

        $envelope = RedEnvelope::query()->findOrFail($envelopeId);
        $this->assertSame(0, $envelope->remaining_count);
        $this->assertTrue($envelope->isFinished());

        // A sixth claimer finds nothing left.
        Sanctum::actingAs(User::factory()->withCoins(0)->create());
        $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $envelopeId,
        ])->assertStatus(422)->assertJsonValidationErrors(['envelope_id']);
    }

    public function test_realtime_events_are_dispatched(): void
    {
        Event::fake([EnvelopeCreated::class, EnvelopeOpened::class, EnvelopeClaimed::class, EnvelopeFinished::class]);

        $creator = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($creator);
        $response = $this->createEnvelope((string) Str::uuid(), ['total_amount' => 200, 'winner_count' => 1])
            ->assertCreated();

        Event::assertDispatched(EnvelopeCreated::class);
        Event::assertDispatched(EnvelopeOpened::class);

        Sanctum::actingAs(User::factory()->withCoins(0)->create());
        $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $response->json('data.envelope.id'),
        ])->assertCreated();

        Event::assertDispatched(EnvelopeClaimed::class);
        Event::assertDispatched(EnvelopeFinished::class);
    }

    public function test_channel_auth_for_red_envelope(): void
    {
        $user = User::factory()->withCoins(100)->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-user.red-envelope.'.$user->id,
            'socket_id' => '1234.5678',
        ])->assertOk();

        $authorized = null;
        foreach (Broadcast::getChannels() as $pattern => $callback) {
            if ($pattern === 'user.red-envelope.{userId}') {
                $authorized = (bool) $callback($user, (string) $other->id);
            }
        }
        $this->assertFalse($authorized);

        $created = new EnvelopeCreated(RedEnvelope::query()->make([
            'creator_id' => $user->id,
            'sender_id' => $user->id,
            'room_id' => (string) Str::uuid(),
            'room_type' => RedEnvelope::ROOM_LIVE,
            'reward_type' => RedEnvelope::REWARD_COINS,
            'total_amount' => 100,
            'remaining_amount' => 100,
            'winner_count' => 2,
            'remaining_count' => 2,
            'status' => RedEnvelope::STATUS_OPEN,
        ]));

        $channels = collect($created->broadcastOn())->map(fn ($channel) => $channel->name)->all();
        $this->assertContains('private-user.games.'.$user->id, $channels);
        $this->assertContains('private-user.red-envelope.'.$user->id, $channels);
        $this->assertSame('red_envelope.created', $created->broadcastAs());
        $this->assertArrayNotHasKey('whitelist_user_ids', $created->broadcastWith());
    }

    public function test_room_envelopes_broadcast_on_a_room_channel(): void
    {
        $creator = User::factory()->withCoins(1000)->create();
        Sanctum::actingAs($creator);

        $roomId = (string) Str::uuid();
        $response = $this->createEnvelope((string) Str::uuid(), [
            'visibility' => RedEnvelope::VIS_LIVE_ROOM,
            'room_type' => RedEnvelope::ROOM_LIVE,
            'room_id' => $roomId,
        ])->assertCreated();

        $envelope = RedEnvelope::query()->findOrFail($response->json('data.envelope.id'));
        $channels = collect((new EnvelopeCreated($envelope))->broadcastOn())
            ->map(fn ($channel) => $channel->name)
            ->all();

        $this->assertContains('private-room.red-envelope.live.'.$roomId, $channels);

        $authorized = null;
        foreach (Broadcast::getChannels() as $pattern => $callback) {
            if ($pattern === 'room.red-envelope.{roomType}.{roomId}') {
                $authorized = (bool) $callback($creator, RedEnvelope::ROOM_LIVE, $roomId);
            }
        }
        $this->assertTrue($authorized);
    }

    public function test_room_envelope_requires_a_room_id(): void
    {
        Sanctum::actingAs(User::factory()->withCoins(1000)->create());

        $this->createEnvelope((string) Str::uuid(), [
            'visibility' => RedEnvelope::VIS_LIVE_ROOM,
            'room_type' => RedEnvelope::ROOM_LIVE,
        ])->assertStatus(422)->assertJsonValidationErrors(['room_id']);
    }

    public function test_history_and_leaderboard_endpoints(): void
    {
        [$creator, $envelopeId] = $this->seedEnvelope(['total_amount' => 300, 'winner_count' => 3]);

        $claimer = User::factory()->withCoins(0)->create();
        Sanctum::actingAs($claimer);
        $this->postJson('/api/v1/games/red-envelope/claim', [
            'idempotency_key' => (string) Str::uuid(),
            'envelope_id' => $envelopeId,
        ])->assertCreated();

        $claimed = $this->getJson('/api/v1/games/red-envelope/history?type=claimed')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'total_amount', 'remaining_amount', 'my_claim']]]);
        $this->assertSame($envelopeId, $claimed->json('data.0.id'));
        $this->assertNotNull($claimed->json('data.0.my_claim.reward_amount'));

        Sanctum::actingAs($creator);
        $created = $this->getJson('/api/v1/games/red-envelope/history?type=created')->assertOk();
        $this->assertSame($envelopeId, $created->json('data.0.id'));
        $this->assertSame(1, $created->json('data.0.claims_count'));

        $this->getJson('/api/v1/games/red-envelope/history?type=all')->assertOk();

        $this->getJson('/api/v1/games/red-envelope/leaderboard?period=daily&type=senders')
            ->assertOk()
            ->assertJsonStructure(['data']);
        $this->getJson('/api/v1/games/red-envelope/leaderboard?period=monthly&type=receivers')->assertOk();

        $receivers = $this->getJson('/api/v1/games/red-envelope/leaderboard?period=daily&type=receivers')
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($receivers);

        $this->assertGreaterThanOrEqual(2, GameHistory::query()->count());
    }

    public function test_guests_cannot_create_envelopes(): void
    {
        $guest = User::factory()->state(['is_guest' => true])->withCoins(1000)->create();
        Sanctum::actingAs($guest);

        $this->createEnvelope((string) Str::uuid(), ['total_amount' => 200])->assertForbidden();
    }

    public function test_inactive_config_blocks_creation(): void
    {
        RedEnvelopeConfig::query()->update(['status' => RedEnvelopeConfig::STATUS_INACTIVE]);

        Sanctum::actingAs(User::factory()->withCoins(1000)->create());
        $this->createEnvelope((string) Str::uuid(), ['total_amount' => 200])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['config']);
    }

    public function test_legacy_generic_play_endpoint_still_creates_and_claims(): void
    {
        $sender = User::factory()->withCoins(1000)->create();
        $claimer = User::factory()->withCoins(100)->create();

        Sanctum::actingAs($sender);
        $create = $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_RED_ENVELOPE,
            'input' => ['action' => 'create', 'total_coins' => 200, 'total_slots' => 2],
        ])->assertCreated();

        $envelopeId = $create->json('data.outcome.envelope_id');
        $envelope = RedEnvelope::query()->findOrFail($envelopeId);

        // The legacy row is backfilled into the plugin columns by the model.
        $this->assertSame($sender->id, $envelope->creator_id);
        $this->assertSame(200, $envelope->total_amount);
        $this->assertSame(2, $envelope->winner_count);
        $this->assertNull($envelope->idempotency_key);

        Sanctum::actingAs($claimer);
        $claim = $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_RED_ENVELOPE,
            'input' => ['action' => 'claim', 'envelope_id' => $envelopeId],
        ])->assertCreated();

        $claimed = (int) $claim->json('data.play.reward_coins');
        $envelope->refresh();
        $this->assertSame(200 - $claimed, $envelope->remaining_coins);
        $this->assertSame(200 - $claimed, $envelope->remaining_amount);
        $this->assertSame(1, $envelope->remaining_slots);
        $this->assertSame(1, $envelope->remaining_count);
    }

    /** @param  array<string, mixed>  $overrides */
    private function createEnvelope(string $idempotencyKey, array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/games/red-envelope/create', array_merge([
            'idempotency_key' => $idempotencyKey,
            'reward_type' => RedEnvelope::REWARD_COINS,
            'distribution_type' => RedEnvelope::DIST_RANDOM,
            'visibility' => RedEnvelope::VIS_PUBLIC,
            'claim_scope' => RedEnvelope::SCOPE_EVERYONE,
            'total_amount' => 300,
            'winner_count' => 3,
            'duration_seconds' => 3600,
            'message' => 'Good luck',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: string}
     */
    private function seedEnvelope(array $overrides = []): array
    {
        $creator = User::factory()->withCoins(100000)->create();
        Sanctum::actingAs($creator);

        $response = $this->createEnvelope((string) Str::uuid(), $overrides)->assertCreated();

        return [$creator, (string) $response->json('data.envelope.id')];
    }
}
