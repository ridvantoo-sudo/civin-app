<?php

namespace Tests\Feature;

use App\Features\Countries\Models\Country;
use App\Features\GeoDiscovery\Models\UserLocation;
use App\Features\GeoDiscovery\Services\DistanceCalculator;
use App\Features\GeoDiscovery\Services\GeoHashService;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Profiles\Models\Profile;
use App\Features\Ranking\Models\Ranking;
use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GeoDiscoverySystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_geo_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/geo')->assertUnauthorized();
        $this->getJson('/api/v1/geo/nearby/users')->assertUnauthorized();
        $this->getJson('/api/v1/geo/nearby/live')->assertUnauthorized();
        $this->putJson('/api/v1/geo/location', [
            'latitude' => 36.19,
            'longitude' => 44.01,
        ])->assertUnauthorized();
    }

    public function test_geohash_and_haversine_helpers(): void
    {
        $geo = new GeoHashService;
        $hash = $geo->encode(36.1911, 44.0092, 6);
        $this->assertSame(6, strlen($hash));
        $this->assertContains($hash, $geo->neighbours($hash));

        $distance = new DistanceCalculator;
        $km = $distance->haversineKm(36.1911, 44.0092, 36.20, 44.02);
        $this->assertTrue($km > 0 && $km < 5);
    }

    public function test_update_location_and_nearby_users(): void
    {
        $kurdistan = Country::query()->create([
            'id' => (string) Str::uuid(),
            'alpha2' => 'KU',
            'alpha3' => 'KRD',
            'name' => 'Kurdistan',
            'phone_code' => '+964',
            'flag_emoji' => '☀️',
            'active' => true,
        ]);

        $viewer = User::factory()->create();
        Profile::factory()->create(['user_id' => $viewer->id, 'country_id' => $kurdistan->id]);
        Sanctum::actingAs($viewer);

        $this->putJson('/api/v1/geo/location', [
            'latitude' => 36.1911,
            'longitude' => 44.0092,
            'share_location' => true,
            'show_distance' => true,
            'nearby_visibility' => 'everyone',
        ])->assertOk()
            ->assertJsonPath('data.has_location', true)
            ->assertJsonPath('data.share_location', true);

        $neighbour = User::factory()->create();
        Profile::factory()->create(['user_id' => $neighbour->id, 'country_id' => $kurdistan->id]);
        $hash = (new GeoHashService)->encode(36.1950, 44.0150, 6);
        UserLocation::query()->create([
            'user_id' => $neighbour->id,
            'latitude' => 36.1950,
            'longitude' => 44.0150,
            'geohash' => $hash,
            'geohash_precision' => 6,
            'share_location' => true,
            'show_distance' => true,
            'nearby_visibility' => UserLocation::VISIBILITY_EVERYONE,
            'located_at' => now(),
        ]);

        $far = User::factory()->create();
        Profile::factory()->create(['user_id' => $far->id, 'country_id' => $kurdistan->id]);
        $farHash = (new GeoHashService)->encode(41.0082, 28.9784, 6);
        UserLocation::query()->create([
            'user_id' => $far->id,
            'latitude' => 41.0082,
            'longitude' => 28.9784,
            'geohash' => $farHash,
            'geohash_precision' => 6,
            'share_location' => true,
            'show_distance' => true,
            'nearby_visibility' => UserLocation::VISIBILITY_EVERYONE,
            'located_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/geo/nearby/users?radius_km=25')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($neighbour->id, $ids);
        $this->assertNotContains($far->id, $ids);
        $this->assertNotNull($response->json('data.0.distance_km'));
    }

    public function test_country_live_voice_and_rankings_for_kurdistan(): void
    {
        $kurdistan = Country::query()->create([
            'id' => (string) Str::uuid(),
            'alpha2' => 'KU',
            'alpha3' => 'KRD',
            'name' => 'Kurdistan',
            'phone_code' => '+964',
            'flag_emoji' => '☀️',
            'active' => true,
        ]);

        $viewer = User::factory()->create();
        Profile::factory()->create(['user_id' => $viewer->id, 'country_id' => $kurdistan->id]);
        Sanctum::actingAs($viewer);

        $host = User::factory()->create();
        Profile::factory()->create(['user_id' => $host->id, 'country_id' => $kurdistan->id]);

        LiveRoom::factory()->create([
            'host_id' => $host->id,
            'status' => 'live',
            'viewer_count' => 12,
            'started_at' => now(),
        ]);
        VoiceRoom::factory()->create([
            'host_id' => $host->id,
            'status' => VoiceRoom::STATUS_LIVE,
            'participant_count' => 4,
            'started_at' => now(),
        ]);
        Ranking::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'HOST_DIAMONDS',
            'period' => 'DAILY',
            'user_id' => $host->id,
            'score' => 100,
            'rank' => 1,
            'date' => now()->toDateString(),
            'created_at' => now(),
        ]);

        $this->getJson('/api/v1/geo/countries/KU/live')
            ->assertOk()
            ->assertJsonPath('data.0.host.id', $host->id);

        $this->getJson('/api/v1/geo/countries/Kurdistan/voice')
            ->assertOk()
            ->assertJsonPath('data.0.host.id', $host->id);

        $this->getJson('/api/v1/geo/countries/KRD/rankings?type=host&period=daily')
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $host->id);
    }

    public function test_privacy_hides_user_from_nearby(): void
    {
        $viewer = User::factory()->create();
        Profile::factory()->create(['user_id' => $viewer->id]);
        Sanctum::actingAs($viewer);

        $this->putJson('/api/v1/geo/location', [
            'latitude' => 36.19,
            'longitude' => 44.01,
        ])->assertOk();

        $hidden = User::factory()->create();
        Profile::factory()->create(['user_id' => $hidden->id]);
        $hash = (new GeoHashService)->encode(36.191, 44.011, 6);
        UserLocation::query()->create([
            'user_id' => $hidden->id,
            'latitude' => 36.191,
            'longitude' => 44.011,
            'geohash' => $hash,
            'geohash_precision' => 6,
            'share_location' => false,
            'show_distance' => false,
            'nearby_visibility' => UserLocation::VISIBILITY_NOBODY,
            'located_at' => now(),
        ]);

        $ids = collect($this->getJson('/api/v1/geo/nearby/users')->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($hidden->id));
    }
}
