<?php

namespace Tests\Feature;

use App\Features\Discovery\Events\OnlineUserUpdated;
use App\Features\LiveStreaming\Models\LiveCategory;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use App\Features\UserStatus\Events\UserStatusChanged;
use App\Features\UserStatus\Models\UserStatus;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiscoverySystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_discovery_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/discovery')->assertUnauthorized();
        $this->getJson('/api/v1/discovery/trending-live')->assertUnauthorized();
        $this->getJson('/api/v1/discovery/trending-voice')->assertUnauthorized();
        $this->getJson('/api/v1/discovery/popular-hosts')->assertUnauthorized();
        $this->getJson('/api/v1/discovery/nearby')->assertUnauthorized();
        $this->getJson('/api/v1/discovery/new-hosts')->assertUnauthorized();
        $this->getJson('/api/v1/discovery/recommended')->assertUnauthorized();
        $this->getJson('/api/v1/discovery/categories')->assertUnauthorized();
        $this->getJson('/api/v1/discovery/search')->assertUnauthorized();
        $this->getJson('/api/v1/discovery/online')->assertUnauthorized();
    }

    public function test_feed_returns_all_discovery_sections(): void
    {
        $viewer = User::factory()->create();
        Profile::factory()->create(['user_id' => $viewer->id]);
        Sanctum::actingAs($viewer);

        $host = User::factory()->create();
        Profile::factory()->create(['user_id' => $host->id, 'followers_count' => 120]);
        UserStatus::factory()->create(['user_id' => $host->id, 'is_online' => true, 'is_live' => true]);

        $category = LiveCategory::factory()->create(['status' => 'active']);
        LiveRoom::factory()->create([
            'host_id' => $host->id,
            'category_id' => $category->id,
            'status' => 'live',
            'viewer_count' => 42,
            'started_at' => now(),
        ]);
        VoiceRoom::factory()->create([
            'host_id' => $host->id,
            'status' => VoiceRoom::STATUS_LIVE,
            'participant_count' => 8,
            'started_at' => now(),
        ]);

        $this->getJson('/api/v1/discovery')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'trending_live',
                    'trending_voice',
                    'popular_hosts',
                    'nearby',
                    'new_hosts',
                    'recommended',
                    'online_users',
                    'categories',
                ],
            ])
            ->assertJsonPath('data.trending_live.0.viewer_count', 42)
            ->assertJsonPath('data.trending_voice.0.participant_count', 8)
            ->assertJsonPath('data.categories.0.id', $category->id);
    }

    public function test_trending_live_is_ordered_by_viewer_count(): void
    {
        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $quiet = User::factory()->create();
        $hot = User::factory()->create();
        Profile::factory()->create(['user_id' => $quiet->id]);
        Profile::factory()->create(['user_id' => $hot->id]);

        LiveRoom::factory()->create([
            'host_id' => $quiet->id,
            'status' => 'live',
            'viewer_count' => 3,
            'started_at' => now(),
        ]);
        $top = LiveRoom::factory()->create([
            'host_id' => $hot->id,
            'status' => 'live',
            'viewer_count' => 99,
            'started_at' => now(),
        ]);

        $this->getJson('/api/v1/discovery/trending-live')
            ->assertOk()
            ->assertJsonPath('data.0.id', $top->id)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_search_users_and_live_rooms(): void
    {
        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $host = User::factory()->create(['username' => 'starhost']);
        Profile::factory()->create(['user_id' => $host->id, 'display_name' => 'Star Host']);
        LiveRoom::factory()->create([
            'host_id' => $host->id,
            'title' => 'Star Night Live',
            'status' => 'live',
            'viewer_count' => 10,
            'started_at' => now(),
        ]);

        $this->getJson('/api/v1/discovery/search?query=starhost')
            ->assertOk()
            ->assertJsonPath('data.0.username', 'starhost');

        $this->getJson('/api/v1/discovery/search?query=Star%20Night&type=live')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Star Night Live');
    }

    public function test_online_users_endpoint_and_status_broadcast(): void
    {
        Event::fake([OnlineUserUpdated::class]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $online = User::factory()->create();
        Profile::factory()->create(['user_id' => $online->id]);
        UserStatus::factory()->create(['user_id' => $online->id, 'is_online' => true]);

        $this->getJson('/api/v1/discovery/online')
            ->assertOk()
            ->assertJsonPath('data.0.id', $online->id);

        $this->patchJson('/api/v1/user-status', ['is_online' => true])
            ->assertOk();

        Event::assertDispatched(OnlineUserUpdated::class);
    }

    public function test_recommended_returns_scored_hosts(): void
    {
        $viewer = User::factory()->create();
        Profile::factory()->create(['user_id' => $viewer->id]);
        Sanctum::actingAs($viewer);

        $popular = User::factory()->create();
        Profile::factory()->create([
            'user_id' => $popular->id,
            'followers_count' => 500,
            'level' => 20,
        ]);
        UserStatus::factory()->create([
            'user_id' => $popular->id,
            'is_online' => true,
            'is_live' => true,
        ]);
        LiveRoom::factory()->create([
            'host_id' => $popular->id,
            'status' => 'live',
            'started_at' => now(),
        ]);

        $this->getJson('/api/v1/discovery/recommended')
            ->assertOk()
            ->assertJsonPath('data.0.id', $popular->id);
    }

    public function test_user_status_changed_triggers_online_broadcast_listener(): void
    {
        Event::fake([OnlineUserUpdated::class]);

        $user = User::factory()->create();
        $status = UserStatus::factory()->create([
            'user_id' => $user->id,
            'is_online' => true,
        ]);

        event(new UserStatusChanged($status));

        Event::assertDispatched(OnlineUserUpdated::class);
    }
}
