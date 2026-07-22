<?php

namespace Tests\Feature;

use App\Features\Countries\Models\Country;
use App\Features\Followers\Models\Follow;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveSession;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\Profiles\Models\Profile;
use App\Features\Ranking\DTOs\RankingQueryData;
use App\Features\Ranking\Events\RankingsCalculated;
use App\Features\Ranking\Jobs\CalculateDailyRankings;
use App\Features\Ranking\Jobs\CalculateWeeklyRankings;
use App\Features\Ranking\Models\Ranking;
use App\Features\Ranking\Models\RankingSnapshot;
use App\Features\Ranking\Services\RankingCacheService;
use App\Features\Ranking\Services\RankingCalculatorService;
use App\Features\Ranking\Services\RankingService;
use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Models\VoiceSession;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RankingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_host_diamonds_ranking_is_calculated_from_gift_earnings(): void
    {
        $topHost = $this->userWithProfile();
        $secondHost = $this->userWithProfile();
        $sender = User::factory()->create();

        GiftTransaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $topHost->id,
            'coins' => 500,
            'created_at' => now(),
        ]);
        GiftTransaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $secondHost->id,
            'coins' => 120,
            'created_at' => now(),
        ]);
        GiftTransaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $topHost->id,
            'coins' => 50,
            'created_at' => now()->subDays(10),
        ]);

        $scores = app(RankingCalculatorService::class)->calculate(new RankingQueryData(
            type: Ranking::TYPE_HOST_DIAMONDS,
            period: Ranking::PERIOD_DAILY,
            limit: 10,
        ));

        $this->assertSame([$topHost->id, $secondHost->id], $scores->pluck('userId')->all());
        $this->assertSame(500, $scores->first()->score);
        $this->assertSame(1, $scores->first()->rank);
        $this->assertSame(120, $scores->get(1)->score);
    }

    public function test_top_gifter_pk_voice_and_popular_rankings_use_expected_sources(): void
    {
        $gifterA = $this->userWithProfile();
        $gifterB = $this->userWithProfile();
        $host = $this->userWithProfile();
        $pkWinner = $this->userWithProfile();
        $pkLoser = $this->userWithProfile();
        $voiceHost = $this->userWithProfile();
        $popular = $this->userWithProfile(['followers_count' => 42]);
        $follower = User::factory()->create();

        GiftTransaction::factory()->create([
            'sender_id' => $gifterA->id,
            'receiver_id' => $host->id,
            'coins' => 300,
            'created_at' => now(),
        ]);
        GiftTransaction::factory()->create([
            'sender_id' => $gifterB->id,
            'receiver_id' => $host->id,
            'coins' => 100,
            'created_at' => now(),
        ]);

        PkBattle::factory()->finished($pkWinner)->create([
            'host_a_id' => $pkWinner->id,
            'host_b_id' => $pkLoser->id,
            'winner_id' => $pkWinner->id,
            'ended_at' => now(),
        ]);
        PkBattle::factory()->finished($pkWinner)->create([
            'host_a_id' => $pkWinner->id,
            'host_b_id' => $pkLoser->id,
            'winner_id' => $pkWinner->id,
            'ended_at' => now(),
        ]);

        $voiceRoom = VoiceRoom::factory()->ended()->create([
            'host_id' => $voiceHost->id,
            'ended_at' => now(),
        ]);
        VoiceSession::factory()->create([
            'room_id' => $voiceRoom->id,
            'duration' => 3600,
        ]);

        Follow::factory()->create([
            'follower_id' => $follower->id,
            'followed_id' => $popular->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $calculator = app(RankingCalculatorService::class);

        $gifters = $calculator->calculate(new RankingQueryData(
            type: Ranking::TYPE_TOP_GIFTER,
            period: Ranking::PERIOD_DAILY,
            limit: 10,
        ));
        $this->assertSame($gifterA->id, $gifters->first()->userId);
        $this->assertSame(300, $gifters->first()->score);

        $pk = $calculator->calculate(new RankingQueryData(
            type: Ranking::TYPE_PK_WINNER,
            period: Ranking::PERIOD_DAILY,
            limit: 10,
        ));
        $this->assertSame($pkWinner->id, $pk->first()->userId);
        $this->assertSame(2, $pk->first()->score);

        $voice = $calculator->calculate(new RankingQueryData(
            type: Ranking::TYPE_VOICE_HOST,
            period: Ranking::PERIOD_DAILY,
            limit: 10,
        ));
        $this->assertSame($voiceHost->id, $voice->first()->userId);
        $this->assertSame(3600, $voice->first()->score);

        $popularScores = $calculator->calculate(new RankingQueryData(
            type: Ranking::TYPE_POPULAR_USER,
            period: Ranking::PERIOD_ALL_TIME,
            limit: 10,
        ));
        $this->assertSame($popular->id, $popularScores->first()->userId);
        $this->assertSame(42, $popularScores->first()->score);
    }

    public function test_period_bounds_exclude_out_of_range_activity(): void
    {
        $host = $this->userWithProfile();
        $sender = User::factory()->create();

        GiftTransaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $host->id,
            'coins' => 200,
            'created_at' => now()->subMonths(2),
        ]);
        GiftTransaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $host->id,
            'coins' => 75,
            'created_at' => now(),
        ]);

        $calculator = app(RankingCalculatorService::class);

        $monthly = $calculator->calculate(new RankingQueryData(
            type: Ranking::TYPE_HOST_DIAMONDS,
            period: Ranking::PERIOD_MONTHLY,
            limit: 10,
        ));
        $this->assertSame(75, $monthly->first()->score);

        $allTime = $calculator->calculate(new RankingQueryData(
            type: Ranking::TYPE_HOST_DIAMONDS,
            period: Ranking::PERIOD_ALL_TIME,
            limit: 10,
        ));
        $this->assertSame(275, $allTime->first()->score);

        $weekly = $calculator->calculate(new RankingQueryData(
            type: Ranking::TYPE_HOST_DIAMONDS,
            period: Ranking::PERIOD_WEEKLY,
            limit: 10,
        ));
        $this->assertSame(75, $weekly->first()->score);
    }

    public function test_ranking_api_endpoints_return_ordered_entries(): void
    {
        $viewer = $this->userWithProfile();
        $topHost = $this->userWithProfile(['display_name' => 'Top Host']);
        $secondHost = $this->userWithProfile(['display_name' => 'Second Host']);
        $topGifter = $this->userWithProfile(['display_name' => 'Big Spender']);
        $pkWinner = $this->userWithProfile(['display_name' => 'PK Champ']);
        $pkLoser = $this->userWithProfile();
        $voiceHost = $this->userWithProfile(['display_name' => 'Voice Star']);

        GiftTransaction::factory()->create([
            'sender_id' => $topGifter->id,
            'receiver_id' => $topHost->id,
            'coins' => 900,
            'created_at' => now(),
        ]);
        GiftTransaction::factory()->create([
            'sender_id' => $viewer->id,
            'receiver_id' => $secondHost->id,
            'coins' => 100,
            'created_at' => now(),
        ]);

        PkBattle::factory()->finished($pkWinner)->create([
            'host_a_id' => $pkWinner->id,
            'host_b_id' => $pkLoser->id,
            'winner_id' => $pkWinner->id,
            'ended_at' => now(),
        ]);

        $room = VoiceRoom::factory()->ended()->create([
            'host_id' => $voiceHost->id,
            'ended_at' => now(),
        ]);
        VoiceSession::factory()->create([
            'room_id' => $room->id,
            'duration' => 1800,
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/rankings/hosts?period=DAILY&limit=10')
            ->assertOk()
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.0.score', 900)
            ->assertJsonPath('data.0.user.id', $topHost->id)
            ->assertJsonPath('data.0.user.nickname', 'Top Host')
            ->assertJsonPath('data.1.user.id', $secondHost->id);

        $this->getJson('/api/v1/rankings/gifters?period=DAILY')
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $topGifter->id)
            ->assertJsonPath('data.0.score', 900);

        $this->getJson('/api/v1/rankings/pk?period=DAILY')
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $pkWinner->id)
            ->assertJsonPath('data.0.score', 1);

        $this->getJson('/api/v1/rankings/voice?period=DAILY')
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $voiceHost->id)
            ->assertJsonPath('data.0.score', 1800);
    }

    public function test_rankings_can_be_filtered_by_country_and_limit(): void
    {
        $tr = Country::factory()->create(['alpha2' => 'TR', 'alpha3' => 'TUR', 'name' => 'Türkiye']);
        $us = Country::factory()->create(['alpha2' => 'US', 'alpha3' => 'USA', 'name' => 'United States']);

        $trHost = $this->userWithProfile(['country_id' => $tr->id, 'display_name' => 'TR Host']);
        $usHost = $this->userWithProfile(['country_id' => $us->id, 'display_name' => 'US Host']);
        $sender = User::factory()->create();

        GiftTransaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $trHost->id,
            'coins' => 400,
            'created_at' => now(),
        ]);
        GiftTransaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $usHost->id,
            'coins' => 800,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($sender);

        $this->getJson('/api/v1/rankings/hosts?period=DAILY&country=TR&limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $trHost->id)
            ->assertJsonPath('data.0.score', 400);
    }

    public function test_ranking_responses_are_cached_and_invalidated_on_recalculate(): void
    {
        $host = $this->userWithProfile();
        $sender = User::factory()->create();

        GiftTransaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $host->id,
            'coins' => 250,
            'created_at' => now(),
        ]);

        $query = new RankingQueryData(
            type: Ranking::TYPE_HOST_DIAMONDS,
            period: Ranking::PERIOD_DAILY,
            limit: 50,
        );

        $cache = app(RankingCacheService::class);
        $service = app(RankingService::class);

        Sanctum::actingAs($sender);
        $first = $service->list($sender, $query);
        $this->assertSame(250, $first->first()->score);
        $this->assertNotNull($cache->get($query));

        GiftTransaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $host->id,
            'coins' => 100,
            'created_at' => now(),
        ]);

        $cached = $service->list($sender, $query);
        $this->assertSame(250, $cached->first()->score);

        Event::fake([RankingsCalculated::class]);
        $service->recalculate(Ranking::TYPE_HOST_DIAMONDS, Ranking::PERIOD_DAILY);

        Event::assertDispatched(RankingsCalculated::class, function (RankingsCalculated $event): bool {
            return $event->type === Ranking::TYPE_HOST_DIAMONDS
                && $event->period === Ranking::PERIOD_DAILY
                && $event->entryCount === 1;
        });

        $this->assertDatabaseHas('rankings', [
            'type' => Ranking::TYPE_HOST_DIAMONDS,
            'period' => Ranking::PERIOD_DAILY,
            'user_id' => $host->id,
            'score' => 350,
            'rank' => 1,
        ]);
        $this->assertDatabaseCount('ranking_snapshots', 1);

        $fresh = $service->list($sender, $query);
        $this->assertSame(350, $fresh->first()->score);
    }

    public function test_scheduled_jobs_persist_daily_and_weekly_rankings(): void
    {
        Event::fake([RankingsCalculated::class]);

        $host = $this->userWithProfile();
        $sender = User::factory()->create();

        GiftTransaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $host->id,
            'coins' => 60,
            'created_at' => now(),
        ]);

        (new CalculateDailyRankings)->handle(app(RankingService::class));
        (new CalculateWeeklyRankings)->handle(app(RankingService::class));

        $this->assertDatabaseHas('rankings', [
            'type' => Ranking::TYPE_HOST_DIAMONDS,
            'period' => Ranking::PERIOD_DAILY,
            'user_id' => $host->id,
            'score' => 60,
        ]);
        $this->assertDatabaseHas('rankings', [
            'type' => Ranking::TYPE_HOST_DIAMONDS,
            'period' => Ranking::PERIOD_WEEKLY,
            'user_id' => $host->id,
            'score' => 60,
        ]);
        $this->assertTrue(
            RankingSnapshot::query()
                ->where('type', Ranking::TYPE_HOST_DIAMONDS)
                ->whereIn('period', [Ranking::PERIOD_DAILY, Ranking::PERIOD_WEEKLY])
                ->count() >= 2,
        );

        Event::assertDispatched(RankingsCalculated::class);
    }

    public function test_rankings_require_authentication_and_reject_invalid_period(): void
    {
        $this->getJson('/api/v1/rankings/hosts')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/rankings/hosts?period=YEARLY')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);

        $this->getJson('/api/v1/rankings/gifters?limit=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_live_duration_and_wallet_sources_are_available_to_repository(): void
    {
        $host = $this->userWithProfile();
        $room = LiveRoom::factory()->create([
            'host_id' => $host->id,
            'status' => 'ended',
            'ended_at' => now(),
        ]);
        LiveSession::factory()->create([
            'room_id' => $room->id,
            'duration' => 2400,
        ]);

        WalletTransaction::factory()->create([
            'user_id' => $host->id,
            'type' => WalletTransaction::TYPE_GIFT_RECEIVED,
            'amount' => 150,
            'currency' => WalletTransaction::CURRENCY_DIAMONDS,
            'created_at' => now(),
        ]);

        $repository = app(\App\Features\Ranking\Repositories\Contracts\RankingRepository::class);
        $live = $repository->aggregateLiveDuration(now()->startOfDay(), now()->endOfDay(), null, 10);

        $this->assertSame($host->id, $live->first()->userId);
        $this->assertSame(2400, $live->first()->score);
    }

    /**
     * @param  array<string, mixed>  $profileAttributes
     */
    private function userWithProfile(array $profileAttributes = []): User
    {
        $user = User::factory()->create();
        Profile::factory()->create(array_merge([
            'user_id' => $user->id,
            'display_name' => $user->username,
        ], $profileAttributes));

        return $user->fresh(['profile.country', 'socialStatus']);
    }
}
