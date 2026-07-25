<?php

namespace Tests\Feature;

use App\Features\SocialEngagement\Models\Achievement;
use App\Features\SocialEngagement\Models\Mentorship;
use App\Features\SocialEngagement\Models\UserAchievement;
use App\Features\SocialEngagement\Support\MentorshipRequestStatus;
use App\Features\SocialEngagement\Support\MentorshipStatus;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocialEngagementSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        config()->set('social_engagement.mentorship.mentor_reward_coins', 50);
        config()->set('social_engagement.mentorship.mentee_reward_coins', 25);
        config()->set('social_engagement.mentorship.session_mentor_coins', 20);
        config()->set('social_engagement.mentorship.session_mentee_coins', 10);
        config()->set('social_engagement.check_in.rewards', [
            1 => ['coins' => 10, 'diamonds' => 0],
            2 => ['coins' => 15, 'diamonds' => 0],
            7 => ['coins' => 100, 'diamonds' => 1],
        ]);
    }

    public function test_social_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/social/mentorships')->assertUnauthorized();
        $this->getJson('/api/v1/social/achievements')->assertUnauthorized();
        $this->getJson('/api/v1/social/check-in')->assertUnauthorized();
        $this->postJson('/api/v1/social/check-in')->assertUnauthorized();
    }

    public function test_daily_check_in_awards_coins_and_blocks_double(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/social/check-in')
            ->assertOk()
            ->assertJsonPath('data.already_checked_in', false);

        $this->postJson('/api/v1/social/check-in')
            ->assertCreated()
            ->assertJsonPath('data.streak_day', 1)
            ->assertJsonPath('data.coins_awarded', 10);

        $this->assertSame(10, (int) Wallet::query()->where('user_id', $user->id)->value('coins_balance'));
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_GROWTH_REWARD,
            'amount' => 10,
        ]);

        $this->postJson('/api/v1/social/check-in')->assertStatus(422);

        $this->getJson('/api/v1/social/check-in')
            ->assertOk()
            ->assertJsonPath('data.already_checked_in', true)
            ->assertJsonPath('data.current_streak', 1);

        // First check-in achievement unlocked.
        $achievement = Achievement::query()->where('code', 'first_check_in')->firstOrFail();
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
            'progress' => 1,
        ]);
        $ua = UserAchievement::query()
            ->where('user_id', $user->id)
            ->where('achievement_id', $achievement->id)
            ->first();
        $this->assertNotNull($ua?->unlocked_at);
    }

    public function test_claim_achievement_credits_wallet(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/social/check-in')->assertCreated();

        $achievement = Achievement::query()->where('code', 'first_check_in')->firstOrFail();
        $before = (int) Wallet::query()->where('user_id', $user->id)->value('coins_balance');

        $this->postJson("/api/v1/social/achievements/{$achievement->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.achievement_id', $achievement->id);

        $this->assertSame(
            $before + (int) $achievement->coins_reward,
            (int) Wallet::query()->where('user_id', $user->id)->value('coins_balance'),
        );

        $this->postJson("/api/v1/social/achievements/{$achievement->id}/claim")->assertStatus(422);
    }

    public function test_mentorship_request_accept_rewards_both_sides(): void
    {
        $mentor = User::factory()->create();
        $mentee = User::factory()->create();

        Sanctum::actingAs($mentee);
        $requestId = $this->postJson('/api/v1/social/mentorships/requests', [
            'target_user_id' => $mentor->id,
            'role' => 'mentee',
            'message' => 'Please mentor me',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($mentor);
        $this->getJson('/api/v1/social/mentorships/requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $requestId);

        $mentorshipId = $this->postJson("/api/v1/social/mentorships/requests/{$requestId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', MentorshipStatus::ACTIVE)
            ->json('data.id');

        $this->assertSame(50, (int) Wallet::query()->where('user_id', $mentor->id)->value('coins_balance'));
        $this->assertSame(25, (int) Wallet::query()->where('user_id', $mentee->id)->value('coins_balance'));
        $this->assertDatabaseHas('mentorship_requests', [
            'id' => $requestId,
            'status' => MentorshipRequestStatus::ACCEPTED,
        ]);

        Sanctum::actingAs($mentor);
        $this->postJson("/api/v1/social/mentorships/{$mentorshipId}/sessions", [
            'duration_minutes' => 30,
            'notes' => 'First session',
        ])->assertCreated();

        $this->assertSame(70, (int) Wallet::query()->where('user_id', $mentor->id)->value('coins_balance'));
        $this->assertSame(35, (int) Wallet::query()->where('user_id', $mentee->id)->value('coins_balance'));

        $this->postJson("/api/v1/social/mentorships/{$mentorshipId}/end")
            ->assertOk()
            ->assertJsonPath('data.status', MentorshipStatus::ENDED);
    }

    public function test_cannot_accept_own_outgoing_request_as_requester(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        Sanctum::actingAs($a);
        $requestId = $this->postJson('/api/v1/social/mentorships/requests', [
            'target_user_id' => $b->id,
            'role' => 'mentee',
        ])->assertCreated()->json('data.id');

        // Requester cannot accept.
        $this->postJson("/api/v1/social/mentorships/requests/{$requestId}/accept")->assertForbidden();
    }

    public function test_achievements_catalog_lists_seeded_items(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $codes = collect($this->getJson('/api/v1/social/achievements')->assertOk()->json('data'))
            ->pluck('code')
            ->all();

        $this->assertContains('first_check_in', $codes);
        $this->assertContains('first_mentorship', $codes);
    }

    public function test_mentee_limit_blocks_second_active_mentor(): void
    {
        config()->set('social_engagement.mentorship.max_active_as_mentee', 1);

        $mentee = User::factory()->create();
        $mentor1 = User::factory()->create();
        $mentor2 = User::factory()->create();

        Mentorship::query()->create([
            'mentor_id' => $mentor1->id,
            'mentee_id' => $mentee->id,
            'status' => MentorshipStatus::ACTIVE,
            'started_at' => now(),
        ]);

        Sanctum::actingAs($mentee);
        $requestId = $this->postJson('/api/v1/social/mentorships/requests', [
            'target_user_id' => $mentor2->id,
            'role' => 'mentee',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($mentor2);
        $this->postJson("/api/v1/social/mentorships/requests/{$requestId}/accept")->assertStatus(422);
    }
}
