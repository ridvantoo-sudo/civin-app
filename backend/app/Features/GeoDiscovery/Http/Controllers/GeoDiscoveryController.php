<?php

namespace App\Features\GeoDiscovery\Http\Controllers;

use App\Features\GeoDiscovery\DTOs\GeoCriteria;
use App\Features\GeoDiscovery\Http\Requests\GeoDiscoveryListRequest;
use App\Features\GeoDiscovery\Http\Requests\UpdateGeoPrivacyRequest;
use App\Features\GeoDiscovery\Http\Requests\UpdateLocationRequest;
use App\Features\GeoDiscovery\Http\Resources\GeoDiscoveryFeedResource;
use App\Features\GeoDiscovery\Http\Resources\GeoNearbyLiveResource;
use App\Features\GeoDiscovery\Http\Resources\GeoNearbyUserResource;
use App\Features\GeoDiscovery\Http\Resources\UserLocationResource;
use App\Features\GeoDiscovery\Services\GeoDiscoveryService;
use App\Features\LiveStreaming\Http\Resources\LiveRoomResource;
use App\Features\Ranking\Http\Resources\RankingEntryResource;
use App\Features\Settings\Models\UserSetting;
use App\Features\VoiceRoom\Http\Resources\VoiceRoomResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class GeoDiscoveryController extends Controller
{
    public function __construct(private readonly GeoDiscoveryService $geo) {}

    public function feed(GeoDiscoveryListRequest $request): JsonResponse
    {
        return response()->json([
            'data' => (new GeoDiscoveryFeedResource(
                $this->geo->feed($request->user(), $this->criteria($request)),
            ))->resolve($request),
        ]);
    }

    public function showLocation(GeoDiscoveryListRequest $request): JsonResponse
    {
        $location = $this->geo->myLocation($request->user());

        return response()->json([
            'data' => $location !== null
                ? (new UserLocationResource($location))->resolve($request)
                : [
                    'share_location' => true,
                    'show_distance' => true,
                    'nearby_visibility' => 'everyone',
                    'geohash_precision' => 6,
                    'located_at' => null,
                    'has_location' => false,
                    'latitude' => null,
                    'longitude' => null,
                    'geohash' => null,
                ],
        ]);
    }

    public function updateLocation(UpdateLocationRequest $request): JsonResponse
    {
        $location = $this->geo->updateLocation(
            $request->user(),
            $request->latitude(),
            $request->longitude(),
            $request->precision(),
            $request->privacy(),
        );

        $this->syncUserSettings($request->user()->getKey(), [
            'location_sharing_enabled' => (bool) $location->share_location,
            'show_distance' => (bool) $location->show_distance,
            'nearby_visibility' => $location->nearby_visibility,
        ]);

        return response()->json([
            'data' => (new UserLocationResource($location))->resolve($request),
        ]);
    }

    public function updatePrivacy(UpdateGeoPrivacyRequest $request): JsonResponse
    {
        $privacy = $request->privacy();
        $location = $this->geo->updatePrivacy($request->user(), $privacy);

        $this->syncUserSettings($request->user()->getKey(), [
            'location_sharing_enabled' => (bool) $location->share_location,
            'show_distance' => (bool) $location->show_distance,
            'nearby_visibility' => $location->nearby_visibility,
        ]);

        return response()->json([
            'data' => (new UserLocationResource($location))->resolve($request),
        ]);
    }

    public function nearbyUsers(GeoDiscoveryListRequest $request): AnonymousResourceCollection
    {
        return GeoNearbyUserResource::collection(
            $this->geo->nearbyUsers($request->user(), $this->criteria($request)),
        );
    }

    public function nearbyLive(GeoDiscoveryListRequest $request): AnonymousResourceCollection
    {
        return GeoNearbyLiveResource::collection(
            $this->geo->nearbyLive($request->user(), $this->criteria($request)),
        );
    }

    public function countryLive(GeoDiscoveryListRequest $request, string $country): AnonymousResourceCollection
    {
        return LiveRoomResource::collection(
            $this->geo->countryLive($request->user(), $country, $this->criteria($request)),
        );
    }

    public function countryVoice(GeoDiscoveryListRequest $request, string $country): AnonymousResourceCollection
    {
        return VoiceRoomResource::collection(
            $this->geo->countryVoiceRooms($request->user(), $country, $this->criteria($request)),
        );
    }

    public function countryRankings(GeoDiscoveryListRequest $request, string $country): AnonymousResourceCollection
    {
        return RankingEntryResource::collection(
            $this->geo->countryRankings(
                $request->user(),
                $country,
                $request->rankingType(),
                $request->period(),
                $request->limit(),
            ),
        );
    }

    private function criteria(GeoDiscoveryListRequest $request): GeoCriteria
    {
        return new GeoCriteria(
            perPage: $request->perPage(),
            radiusKm: $request->radiusKm(),
            latitude: $request->latitude(),
            longitude: $request->longitude(),
            country: $request->country(),
            period: $request->period(),
            rankingType: $request->rankingType(),
            limit: $request->limit(),
        );
    }

    /** @param array<string, mixed> $values */
    private function syncUserSettings(string $userId, array $values): void
    {
        foreach ($values as $key => $value) {
            UserSetting::query()->updateOrCreate(
                ['user_id' => $userId, 'key' => $key],
                ['value' => $value],
            );
        }
    }
}
