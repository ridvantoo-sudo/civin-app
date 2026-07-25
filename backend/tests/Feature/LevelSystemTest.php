<?php

namespace Tests\Feature;

use App\Features\Authentication\Events\UserLoggedIn;
use App\Features\Gifts\Events\GiftSent;
use App\Features\Gifts\Models\Gift;
use App\Features\Gifts\Models\GiftCategory;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Levels\Listeners\AwardXpFromGift;
use App\Features\Levels\Listeners\AwardXpFromLiveEnded;
use App\Features\Levels\Listeners\AwardXpFromLogin;
use App\Features\Levels\Models\Badge;
use App\Features\Levels\Models\DailyTask;
use App\Features\Levels\Models\UserLevelProgress;
use App\Features\Levels\Models\XpTransaction;
use App\Features\Levels\Services\LevelService;
use App\Features\Levels\Support\XpCurve;
use App\Features\LiveStreaming\Events\LiveEnded;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveViewer;
use App\Features\Profiles\Models\Profile;
use App\Features\Ranking\Models\Ranking;
use App\Features\Users\Models\User;
use Database\Seeders\LevelSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LevelSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(LevelSystemSeeder::class);
    }

    public function test_xp_curve_supports_levels_one_to_one_hundred(): void
    {
        $this->assertSame(1, XpCurve::minLevel());
        $this->assertSame(100, XpCurve::maxLevel());
        $this->assertSame(100, XpCurve::xpRequiredForLevel(1));
        $this->assertSame(0, XpCurve::xpRequiredForLevel(100));

        $resolved = XpCurve::resolveFromTotalXp(100);
        $this->assertSame(2, $resolved['level']);
        $this->assertSame(0, $resolved['xp_into_level']);
    }

    public function test_levels_me_returns_progress_for_authenticated_user(): void
    {
        $user = $this->userWithProfile();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/levels/me')
            ->assertOk()
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.xp_total', 0)
            ->assertJsonPath('data.max_level', 100)
            ->assertJsonPath('data.is_max_level', false);
    }

    public function test_login_awards_daily_login_xp_once(): void
    {
        $user = $this->userWithProfile();
        $listener = app(AwardXpFromLogin::class);

        $listener->handle(new UserLoggedIn($user));
        $listener->handle(new UserLoggedIn($user));

        $this->assertDatabaseCount('xp_transactions', 1);
        $this->assertSame(50, (int) UserLevelProgress::query()->where('user_id', $user->id)->value('xp_total'));
    }

    public function test_gift_sent_awards_sender_and_receiver_xp(): void
    {
        $sender = $this->userWithProfile();
        $receiver = $this->userWithProfile();
        $category = GiftCategory::factory()->create();
        $gift = Gift::factory()->create([
            'category_id' => $category->id,
            'coin_price' => 20,
        ]);
        $transaction = GiftTransaction::query()->create([
            'id' => (string) Str::uuid(),
            'gift_id' => $gift->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'quantity' => 2,
            'coins' => 40,
            'created_at' => now(),
        ]);

        app(AwardXpFromGift::class)->handle(new GiftSent($transaction));

        $this->assertSame(40, (int) UserLevelProgress::query()->where('user_id', $sender->id)->value('xp_total'));
        $this->assertSame(20, (int) UserLevelProgress::query()->where('user_id', $receiver->id)->value('xp_total'));
        $this->assertDatabaseHas('xp_transactions', [
            'user_id' => $sender->id,
            'source' => XpTransaction::SOURCE_GIFT_SEND,
            'amount' => 40,
        ]);
        $this->assertDatabaseHas('xp_transactions', [
            'user_id' => $receiver->id,
            'source' => XpTransaction::SOURCE_GIFT_RECEIVE,
            'amount' => 20,
        ]);
    }

    public function test_live_ended_awards_host_and_viewer_xp(): void
    {
        $host = $this->userWithProfile();
        $viewer = $this->userWithProfile();
        $room = LiveRoom::factory()->create([
            'host_id' => $host->id,
            'status' => 'ended',
        ]);
        LiveViewer::query()->create([
            'room_id' => $room->id,
            'user_id' => $viewer->id,
            'joined_at' => now()->subMinutes(5),
            'left_at' => now(),
        ]);

        app(AwardXpFromLiveEnded::class)->handle(new LiveEnded(
            roomId: (string) $room->id,
            hostId: (string) $host->id,
            durationSeconds: 300,
        ));

        $this->assertSame(50, (int) UserLevelProgress::query()->where('user_id', $host->id)->value('xp_total'));
        $this->assertSame(10, (int) UserLevelProgress::query()->where('user_id', $viewer->id)->value('xp_total'));
    }

    public function test_daily_task_can_be_claimed_after_completion(): void
    {
        $user = $this->userWithProfile();
        $levels = app(LevelService::class);
        $levels->awardLogin($user);

        $task = DailyTask::query()->where('code', 'daily_login')->firstOrFail();

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/levels/daily-tasks')
            ->assertOk()
            ->assertJsonFragment([
                'code' => 'daily_login',
                'is_completed' => true,
                'can_claim' => true,
            ]);

        $this->postJson('/api/v1/levels/daily-tasks/claim', [
            'task_id' => $task->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.awarded', true)
            ->assertJsonPath('data.amount', 30);

        $this->postJson('/api/v1/levels/daily-tasks/claim', [
            'task_id' => $task->id,
        ])->assertStatus(422);
    }

    public function test_level_up_unlocks_badge_and_awards_bonus_xp(): void
    {
        $user = $this->userWithProfile();
        $levels = app(LevelService::class);

        $levels->award(
            user: $user,
            source: XpTransaction::SOURCE_LIVE,
            amount: XpCurve::totalXpForLevel(5),
            idempotencyKey: 'test-level-5',
        );

        $this->assertGreaterThanOrEqual(5, (int) $user->profile()->value('level'));
        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => Badge::query()->where('code', 'newcomer')->value('id'),
        ]);
        $this->assertDatabaseHas('xp_transactions', [
            'user_id' => $user->id,
            'source' => XpTransaction::SOURCE_BADGE,
        ]);
    }

    public function test_level_rankings_endpoint_returns_xp_leaders(): void
    {
        $first = $this->userWithProfile();
        $second = $this->userWithProfile();
        app(LevelService::class)->award($first, XpTransaction::SOURCE_LOGIN, 200, 'rank-a');
        app(LevelService::class)->award($second, XpTransaction::SOURCE_LOGIN, 50, 'rank-b');

        Sanctum::actingAs($first);
        $this->getJson('/api/v1/rankings/levels?period=ALL_TIME')
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $first->id)
            ->assertJsonPath('data.0.score', 200);
    }

    public function test_profile_includes_level_progress_fields(): void
    {
        $user = $this->userWithProfile();
        app(LevelService::class)->award($user, XpTransaction::SOURCE_LOGIN, 40, 'profile-xp');

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.xp_total', 40)
            ->assertJsonPath('data.xp_into_level', 40)
            ->assertJsonPath('data.xp_to_next', 100);
    }

    public function test_ranking_type_includes_user_level(): void
    {
        $this->assertContains(Ranking::TYPE_USER_LEVEL, Ranking::TYPES);
    }

    private function userWithProfile(array $profileAttributes = []): User
    {
        $user = User::factory()->create();

        Profile::factory()->create(array_merge([
            'user_id' => $user->id,
            'display_name' => $user->username,
            'level' => 1,
        ], $profileAttributes));

        return $user->fresh(['profile']);
    }
}
