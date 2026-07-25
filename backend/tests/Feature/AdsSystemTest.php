<?php

namespace Tests\Feature;

use App\Features\Ads\Models\AdCampaign;
use App\Features\Ads\Models\AdEvent;
use App\Features\Levels\Models\UserLevelProgress;
use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdsSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        config(['ads.min_creator_level' => 10, 'ads.allow_svip' => true]);
    }

    public function test_eligibility_locked_for_low_level_non_svip(): void
    {
        Sanctum::actingAs($this->userWithLevel(3, isSvip: false));

        $this->getJson('/api/v1/ads/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.min_level', 10)
            ->assertJsonPath('data.user_level', 3);
    }

    public function test_eligibility_unlocked_by_level_or_svip(): void
    {
        Sanctum::actingAs($this->userWithLevel(12, isSvip: false));
        $this->getJson('/api/v1/ads/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', true);

        Sanctum::actingAs($this->userWithLevel(2, isSvip: true));
        $this->getJson('/api/v1/ads/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.is_svip', true);
    }

    public function test_low_level_user_cannot_create_ads(): void
    {
        Sanctum::actingAs($this->userWithLevel(4, isSvip: false));

        $this->postJson('/api/v1/ads', [
            'title' => 'My promo',
            'placement' => AdCampaign::PLACEMENT_FEED_POST,
            'status' => AdCampaign::STATUS_ACTIVE,
        ])->assertForbidden();
    }

    public function test_eligible_user_can_create_pause_resume_and_track_ads(): void
    {
        $user = $this->userWithLevel(15, isSvip: false);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/ads', [
            'title' => 'Feed promo',
            'body' => 'Sponsored discovery card',
            'placement' => AdCampaign::PLACEMENT_FEED_POST,
            'media_url' => 'https://cdn.example.com/ad.jpg',
            'cta_label' => 'Open',
            'cta_url' => 'https://example.com',
            'status' => AdCampaign::STATUS_ACTIVE,
            'priority' => 5,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Feed promo')
            ->assertJsonPath('data.placement', AdCampaign::PLACEMENT_FEED_POST)
            ->assertJsonPath('data.is_sponsored', true);

        $adId = (string) $create->json('data.id');

        $this->getJson('/api/v1/ads/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/ads/placements/feed_post')
            ->assertOk()
            ->assertJsonPath('data.0.id', $adId)
            ->assertJsonPath('data.0.badge', 'Sponsored');

        $this->postJson("/api/v1/ads/{$adId}/events", [
            'event_type' => AdEvent::TYPE_IMPRESSION,
        ])->assertCreated();

        $this->postJson("/api/v1/ads/{$adId}/events", [
            'event_type' => AdEvent::TYPE_CLICK,
        ])->assertCreated();

        $this->getJson("/api/v1/ads/{$adId}/analytics")
            ->assertOk()
            ->assertJsonPath('data.impressions', 1)
            ->assertJsonPath('data.clicks', 1);

        $this->postJson("/api/v1/ads/{$adId}/pause")
            ->assertOk()
            ->assertJsonPath('data.status', AdCampaign::STATUS_PAUSED);

        $this->getJson('/api/v1/ads/placements/feed_post')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/ads/{$adId}/resume")
            ->assertOk()
            ->assertJsonPath('data.status', AdCampaign::STATUS_ACTIVE);
    }

    public function test_svip_user_can_manage_live_and_chat_ads(): void
    {
        Sanctum::actingAs($this->userWithLevel(1, isSvip: true));

        $live = $this->postJson('/api/v1/ads', [
            'title' => 'Live banner',
            'placement' => AdCampaign::PLACEMENT_LIVE_BANNER,
            'status' => AdCampaign::STATUS_ACTIVE,
            'target_type' => AdCampaign::TARGET_LIVE_ROOM,
            'target_payload' => ['ids' => ['room-1']],
        ])->assertCreated();

        $chat = $this->postJson('/api/v1/ads', [
            'title' => 'Chat promo',
            'placement' => AdCampaign::PLACEMENT_CHAT_BANNER,
            'status' => AdCampaign::STATUS_ACTIVE,
        ])->assertCreated();

        $this->getJson('/api/v1/ads/placements/live_banner?context_id=room-1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $live->json('data.id'));

        $this->getJson('/api/v1/ads/placements/live_banner?context_id=other-room')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/ads/placements/chat_banner')
            ->assertOk()
            ->assertJsonPath('data.0.id', $chat->json('data.id'));
    }

    public function test_admin_campaigns_are_served_without_owner(): void
    {
        $campaign = AdCampaign::factory()->feedPost()->create([
            'title' => 'Platform ad',
            'is_admin_campaign' => true,
            'owner_user_id' => null,
            'status' => AdCampaign::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($this->userWithLevel(1, isSvip: false));

        $this->getJson('/api/v1/ads/placements/feed_post')
            ->assertOk()
            ->assertJsonPath('data.0.id', $campaign->id)
            ->assertJsonPath('data.0.is_admin_campaign', true);
    }

    private function userWithLevel(int $level, bool $isSvip = false): User
    {
        $user = User::factory()->create(['is_guest' => false, 'status' => User::STATUS_ACTIVE]);

        Profile::factory()->create([
            'user_id' => $user->id,
            'display_name' => $user->username,
            'level' => $level,
            'is_vip' => false,
            'is_svip' => $isSvip,
        ]);

        UserLevelProgress::query()->create([
            'user_id' => $user->id,
            'level' => $level,
            'xp_total' => $level * 100,
            'xp_into_level' => 0,
        ]);

        return $user->fresh(['profile']);
    }
}
