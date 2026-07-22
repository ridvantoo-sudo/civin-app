<?php

namespace Tests\Feature;

use App\Features\Countries\Models\Country;
use App\Features\Followers\Events\FollowRequested;
use App\Features\Followers\Events\UserFollowed;
use App\Features\Followers\Notifications\FollowRequestNotification;
use App\Features\Followers\Notifications\NewFollowerNotification;
use App\Features\Profiles\Models\Profile;
use App\Features\Reports\Events\ReportReviewed;
use App\Features\Reports\Events\UserReported;
use App\Features\Reports\Models\Report;
use App\Features\Reports\Notifications\ReportReviewedNotification;
use App\Features\Users\Models\User;
use App\Features\UserStatus\Events\UserStatusChanged;
use App\Features\UserStatus\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserSocialSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_fields_and_user_status_can_be_updated_and_viewed(): void
    {
        [$viewer] = $this->socialUser('viewer');
        [$target] = $this->socialUser('target');
        Sanctum::actingAs($target);
        Event::fake([UserStatusChanged::class]);

        $this->patchJson('/api/v1/profile', [
            'nickname' => 'Target Nick',
            'bio' => 'A public bio',
            'avatar_url' => 'https://cdn.example.com/avatar.jpg',
            'cover_image_url' => 'https://cdn.example.com/cover.jpg',
            'birthday' => '2000-01-02',
            'gender' => 'non_binary',
            'is_private' => true,
        ])->assertOk()
            ->assertJsonPath('data.nickname', 'Target Nick')
            ->assertJsonPath('data.cover_image_url', 'https://cdn.example.com/cover.jpg')
            ->assertJsonPath('data.is_private', true);

        $this->patchJson('/api/v1/user-status', ['is_online' => true, 'is_live' => true])
            ->assertOk()
            ->assertJsonPath('data.is_online', true)
            ->assertJsonPath('data.is_live', true);
        Event::assertDispatched(UserStatusChanged::class);

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/users/{$target->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.username', 'target')
            ->assertJsonPath('data.nickname', 'Target Nick')
            ->assertJsonPath('data.is_online', true)
            ->assertJsonPath('data.is_live', true);
    }

    public function test_public_follow_is_idempotent_notifies_and_maintains_counters(): void
    {
        [$follower, $followerProfile] = $this->socialUser('follower');
        [$target, $targetProfile] = $this->socialUser('target');
        Sanctum::actingAs($follower);
        Notification::fake();
        Event::fake([UserFollowed::class]);

        $this->postJson("/api/v1/users/{$target->id}/follow")
            ->assertCreated()
            ->assertJsonPath('data.status', 'accepted');
        $this->postJson("/api/v1/users/{$target->id}/follow")->assertOk();
        $this->getJson("/api/v1/users/{$target->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.follow_status', 'accepted')
            ->assertJsonPath('data.is_blocked', false);

        $this->assertDatabaseCount('followers', 1);
        $this->assertSame(1, $followerProfile->fresh()->following_count);
        $this->assertSame(1, $targetProfile->fresh()->followers_count);
        Notification::assertSentTo($target, NewFollowerNotification::class);
        Event::assertDispatched(UserFollowed::class);

        $this->getJson("/api/v1/users/{$target->id}/followers")
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $follower->id);
        $this->getJson("/api/v1/users/{$follower->id}/following")
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $target->id);

        $this->deleteJson("/api/v1/users/{$target->id}/follow")->assertNoContent();
        $this->assertSoftDeleted('followers', ['follower_id' => $follower->id, 'followed_id' => $target->id]);
        $this->assertSame(0, $followerProfile->fresh()->following_count);
        $this->assertSame(0, $targetProfile->fresh()->followers_count);
    }

    public function test_private_accounts_use_follow_requests_and_owner_only_responses(): void
    {
        [$requester] = $this->socialUser('requester');
        [$owner] = $this->socialUser('owner', ['is_private' => true]);
        [$stranger] = $this->socialUser('stranger');
        Notification::fake();
        Event::fake([FollowRequested::class, UserFollowed::class]);

        Sanctum::actingAs($requester);
        $response = $this->postJson("/api/v1/users/{$owner->id}/follow")
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
        $followId = $response->json('data.id');
        Notification::assertSentTo($owner, FollowRequestNotification::class);
        $this->getJson("/api/v1/users/{$owner->id}/followers")->assertForbidden();

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/follower-requests/{$followId}/accept")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/follower-requests')
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $requester->id);
        $this->postJson("/api/v1/follower-requests/{$followId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        Sanctum::actingAs($requester);
        $this->getJson("/api/v1/users/{$owner->id}/followers")->assertOk();
        $this->assertSame(1, $owner->profile->fresh()->followers_count);
        Event::assertDispatched(FollowRequested::class);
        Event::assertDispatched(UserFollowed::class);
    }

    public function test_blocking_removes_relationships_and_prevents_social_interaction(): void
    {
        [$first] = $this->socialUser('first');
        [$second] = $this->socialUser('second');
        Sanctum::actingAs($first);

        $this->postJson("/api/v1/users/{$second->id}/follow")->assertCreated();
        $this->postJson("/api/v1/users/{$second->id}/block")->assertCreated();

        $this->assertDatabaseHas('blocks', ['blocker_id' => $first->id, 'blocked_id' => $second->id]);
        $this->assertSoftDeleted('followers', ['follower_id' => $first->id, 'followed_id' => $second->id]);
        $this->assertSame(0, $first->profile->fresh()->following_count);
        $this->assertSame(0, $second->profile->fresh()->followers_count);
        $this->postJson("/api/v1/users/{$second->id}/follow")->assertForbidden();
        $this->getJson("/api/v1/users/{$second->id}/profile")->assertForbidden();
        $this->getJson('/api/v1/users/search?query=second')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/blocks')->assertOk()->assertJsonPath('data.0.user.id', $second->id);

        $this->deleteJson("/api/v1/users/{$second->id}/block")->assertNoContent();
        $this->assertSoftDeleted('blocks', ['blocker_id' => $first->id, 'blocked_id' => $second->id]);
        $this->postJson("/api/v1/users/{$second->id}/follow")->assertOk();
    }

    public function test_reports_have_categories_history_and_admin_review(): void
    {
        [$reporter] = $this->socialUser('reporter');
        [$reported] = $this->socialUser('reported');
        [$admin] = $this->socialUser('admin');
        $admin->forceFill(['is_admin' => true])->save();
        Notification::fake();
        Event::fake([UserReported::class, ReportReviewed::class]);

        Sanctum::actingAs($reporter);
        $this->getJson('/api/v1/report-categories')
            ->assertOk()
            ->assertJsonFragment(['harassment']);
        $response = $this->postJson("/api/v1/users/{$reported->id}/reports", [
            'category' => 'harassment',
            'details' => 'Repeated abusive messages.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending');
        $reportId = $response->json('data.id');
        $this->getJson('/api/v1/reports/history')
            ->assertOk()
            ->assertJsonPath('data.0.id', $reportId);
        $this->getJson('/api/v1/admin/reports')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/reports')->assertOk()->assertJsonPath('data.0.id', $reportId);
        $this->patchJson("/api/v1/admin/reports/{$reportId}", [
            'status' => 'resolved',
            'review_notes' => 'Action completed.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.review_notes', 'Action completed.');

        Notification::assertSentTo($reporter, ReportReviewedNotification::class);
        Event::assertDispatched(UserReported::class);
        Event::assertDispatched(ReportReviewed::class);
    }

    public function test_search_filters_username_nickname_id_country_and_online_status(): void
    {
        $country = Country::factory()->create(['alpha2' => 'TR', 'alpha3' => 'TUR', 'name' => 'Türkiye']);
        [$viewer] = $this->socialUser('viewer');
        [$match, $profile] = $this->socialUser('alice');
        $profile->update(['display_name' => 'Wonder Person', 'country_id' => $country->id]);
        UserStatus::factory()->create(['user_id' => $match->id, 'is_online' => true]);
        $this->socialUser('bob');
        Sanctum::actingAs($viewer);

        foreach ([
            'query=alice',
            'query=Wonder',
            "query={$match->id}",
            'country=TR',
            'is_online=1',
        ] as $query) {
            $this->getJson("/api/v1/users/search?{$query}")
                ->assertOk()
                ->assertJsonPath('data.0.id', $match->id);
        }
    }

    public function test_invalid_self_actions_and_report_review_authorization_are_rejected(): void
    {
        [$user] = $this->socialUser('person');
        [$other] = $this->socialUser('other');
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/users/{$user->id}/follow")->assertUnprocessable();
        $this->postJson("/api/v1/users/{$user->id}/block")->assertUnprocessable();
        $this->postJson("/api/v1/users/{$user->id}/reports", [
            'category' => 'spam',
        ])->assertUnprocessable();

        $report = Report::factory()->create([
            'reporter_id' => $other->id,
            'reported_user_id' => $user->id,
        ]);
        $this->patchJson("/api/v1/admin/reports/{$report->id}", ['status' => 'dismissed'])
            ->assertForbidden();
    }

    private function socialUser(string $username, array $profileAttributes = []): array
    {
        $user = User::factory()->create(['username' => $username]);
        $profile = Profile::factory()->create([
            'user_id' => $user->id,
            'display_name' => ucfirst($username),
            ...$profileAttributes,
        ]);

        return [$user->fresh(), $profile];
    }
}
