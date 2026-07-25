<?php

namespace Tests\Feature;

use App\Features\Authentication\Events\UserLoggedIn;
use App\Features\Challenges\Listeners\TrackChallengeFromGift;
use App\Features\Challenges\Listeners\TrackChallengeFromLogin;
use App\Features\Challenges\Listeners\TrackChallengeFromPk;
use App\Features\Challenges\Models\Challenge;
use App\Features\Challenges\Models\UserChallengeProgress;
use App\Features\Challenges\Services\ChallengeService;
use App\Features\Gifts\Events\GiftSent;
use App\Features\Gifts\Models\Gift;
use App\Features\Gifts\Models\GiftCategory;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Levels\Models\XpTransaction;
use App\Features\PkBattle\Events\PkFinished;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use Database\Seeders\ChallengeSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChallengeSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(ChallengeSystemSeeder::class);
    }

    public function test_lists_daily_and_weekly_challenges(): void
    {
        $user = $this->userWithProfile();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/challenges/daily')
            ->assertOk()
            ->assertJsonFragment(['code' => 'daily_gift', 'period' => 'daily'])
            ->assertJsonFragment(['code' => 'daily_pk', 'task_type' => 'pk']);

        $this->getJson('/api/v1/challenges/weekly')
            ->assertOk()
            ->assertJsonFragment(['code' => 'weekly_watch', 'period' => 'weekly'])
            ->assertJsonFragment(['code' => 'weekly_pk', 'task_type' => 'pk']);
    }

    public function test_login_tracks_daily_check_in_challenge(): void
    {
        $user = $this->userWithProfile();
        app(TrackChallengeFromLogin::class)->handle(new UserLoggedIn($user));

        $challenge = Challenge::query()->where('code', 'daily_login')->firstOrFail();
        $progress = UserChallengeProgress::query()
            ->where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->first();

        $this->assertNotNull($progress);
        $this->assertTrue($progress->isCompleted());
        $this->assertFalse($progress->isClaimed());
    }

    public function test_gift_tracks_gift_challenge_progress(): void
    {
        $sender = $this->userWithProfile();
        $receiver = $this->userWithProfile();
        $category = GiftCategory::factory()->create();
        $gift = Gift::factory()->create([
            'category_id' => $category->id,
            'coin_price' => 50,
        ]);
        $transaction = GiftTransaction::query()->create([
            'id' => (string) Str::uuid(),
            'gift_id' => $gift->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'quantity' => 2,
            'coins' => 100,
            'created_at' => now(),
        ]);

        app(TrackChallengeFromGift::class)->handle(new GiftSent($transaction));

        $daily = Challenge::query()->where('code', 'daily_gift')->firstOrFail();
        $this->assertDatabaseHas('user_challenge_progress', [
            'user_id' => $sender->id,
            'challenge_id' => $daily->id,
            'progress' => 100,
        ]);
    }

    public function test_pk_finished_tracks_pk_challenges(): void
    {
        $hostA = $this->userWithProfile();
        $hostB = $this->userWithProfile();
        $battle = PkBattle::factory()->create([
            'host_a_id' => $hostA->id,
            'host_b_id' => $hostB->id,
            'status' => PkBattle::STATUS_FINISHED,
            'ended_at' => now(),
            'winner_id' => $hostA->id,
        ]);

        app(TrackChallengeFromPk::class)->handle(new PkFinished($battle));

        $daily = Challenge::query()->where('code', 'daily_pk')->firstOrFail();
        $this->assertDatabaseHas('user_challenge_progress', [
            'user_id' => $hostA->id,
            'challenge_id' => $daily->id,
            'progress' => 1,
        ]);
        $this->assertDatabaseHas('user_challenge_progress', [
            'user_id' => $hostB->id,
            'challenge_id' => $daily->id,
            'progress' => 1,
        ]);
    }

    public function test_claim_awards_xp_coins_and_diamonds(): void
    {
        $user = $this->userWithProfile();
        Wallet::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['coins_balance' => 0, 'diamonds_balance' => 0],
        );

        $challenge = Challenge::query()->where('code', 'daily_gift')->firstOrFail();
        $periodKey = app(ChallengeService::class)->periodKey($challenge->period);

        UserChallengeProgress::query()->create([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'period_key' => $periodKey,
            'progress' => $challenge->target,
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/challenges/claim', [
            'challenge_id' => $challenge->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.claimed', true)
            ->assertJsonPath('data.xp_awarded', 60)
            ->assertJsonPath('data.coins_awarded', 20)
            ->assertJsonPath('data.diamonds_awarded', 2);

        $this->assertDatabaseHas('xp_transactions', [
            'user_id' => $user->id,
            'source' => XpTransaction::SOURCE_CHALLENGE,
            'amount' => 60,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_CHALLENGE_REWARD,
            'currency' => WalletTransaction::CURRENCY_COINS,
            'amount' => 20,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_CHALLENGE_REWARD,
            'currency' => WalletTransaction::CURRENCY_DIAMONDS,
            'amount' => 2,
        ]);

        $this->getJson('/api/v1/challenges/history')
            ->assertOk()
            ->assertJsonFragment([
                'code' => 'daily_gift',
                'xp_awarded' => 60,
                'coins_awarded' => 20,
                'diamonds_awarded' => 2,
            ]);

        $this->postJson('/api/v1/challenges/claim', [
            'challenge_id' => $challenge->id,
        ])->assertStatus(422);
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
