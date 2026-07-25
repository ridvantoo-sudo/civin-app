<?php

namespace App\Features\GeoDiscovery\Services;

use App\Features\GeoDiscovery\DTOs\GeoCriteria;
use App\Features\GeoDiscovery\Models\UserLocation;
use App\Features\GeoDiscovery\Repositories\Contracts\GeoDiscoveryRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class GeoDiscoveryService
{
    public function __construct(
        private readonly GeoDiscoveryRepository $geo,
        private readonly GeoHashService $geoHash,
    ) {}

    public function updateLocation(
        User $user,
        float $latitude,
        float $longitude,
        int $precision = 6,
        ?array $privacy = null,
    ): UserLocation {
        $precision = max(4, min(8, $precision));
        $geohash = $this->geoHash->encode($latitude, $longitude, $precision);

        return $this->geo->upsertLocation(
            $user,
            $latitude,
            $longitude,
            $geohash,
            $precision,
            $privacy,
        );
    }

    public function myLocation(User $user): ?UserLocation
    {
        return $this->geo->findLocation($user);
    }

    public function updatePrivacy(User $user, array $privacy): UserLocation
    {
        return $this->geo->updatePrivacy($user, $privacy);
    }

    public function nearbyUsers(User $viewer, GeoCriteria $criteria): LengthAwarePaginator
    {
        return $this->geo->nearbyUsers($viewer, $this->requireOrigin($viewer, $criteria), $criteria);
    }

    public function nearbyLive(User $viewer, GeoCriteria $criteria): LengthAwarePaginator
    {
        return $this->geo->nearbyLive($viewer, $this->requireOrigin($viewer, $criteria), $criteria);
    }

    public function countryLive(User $viewer, string $country, GeoCriteria $criteria): LengthAwarePaginator
    {
        return $this->geo->countryLive($viewer, $country, $criteria);
    }

    public function countryVoiceRooms(User $viewer, string $country, GeoCriteria $criteria): LengthAwarePaginator
    {
        return $this->geo->countryVoiceRooms($viewer, $country, $criteria);
    }

    public function countryRankings(
        User $viewer,
        string $country,
        string $type,
        string $period,
        int $limit,
    ): Collection {
        return $this->geo->countryRankings($viewer, $country, $type, $period, $limit);
    }

    /**
     * @return array{
     *     nearby_users: LengthAwarePaginator,
     *     nearby_live: LengthAwarePaginator,
     *     country_live: LengthAwarePaginator,
     *     country_voice: LengthAwarePaginator,
     *     country_rankings: Collection,
     *     location: ?UserLocation,
     * }
     */
    public function feed(User $viewer, GeoCriteria $criteria): array
    {
        $location = $this->geo->findLocation($viewer);
        $country = $criteria->country
            ?? $viewer->profile?->country?->alpha2
            ?? $viewer->profile?->country_id;

        $nearbyUsers = $location !== null && $location->share_location
            ? $this->geo->nearbyUsers($viewer, $location, $criteria)
            : $this->emptyPage($criteria->perPage);

        $nearbyLive = $location !== null && $location->share_location
            ? $this->geo->nearbyLive($viewer, $location, $criteria)
            : $this->emptyPage($criteria->perPage);

        $countryLive = $country
            ? $this->geo->countryLive($viewer, (string) $country, $criteria)
            : $this->emptyPage($criteria->perPage);

        $countryVoice = $country
            ? $this->geo->countryVoiceRooms($viewer, (string) $country, $criteria)
            : $this->emptyPage($criteria->perPage);

        $countryRankings = $country
            ? $this->geo->countryRankings(
                $viewer,
                (string) $country,
                $criteria->rankingType,
                $criteria->period,
                min($criteria->limit, 20),
            )
            : collect();

        return [
            'nearby_users' => $nearbyUsers,
            'nearby_live' => $nearbyLive,
            'country_live' => $countryLive,
            'country_voice' => $countryVoice,
            'country_rankings' => $countryRankings,
            'location' => $location,
        ];
    }

    private function requireOrigin(User $viewer, GeoCriteria $criteria): UserLocation
    {
        if ($criteria->latitude !== null && $criteria->longitude !== null) {
            $precision = $this->geoHash->precisionForRadiusKm($criteria->radiusKm);
            $precision = max(4, min(8, $precision));
            $geohash = $this->geoHash->encode($criteria->latitude, $criteria->longitude, $precision);

            return new UserLocation([
                'user_id' => $viewer->getKey(),
                'latitude' => $criteria->latitude,
                'longitude' => $criteria->longitude,
                'geohash' => $geohash,
                'geohash_precision' => $precision,
                'share_location' => true,
                'show_distance' => true,
                'nearby_visibility' => UserLocation::VISIBILITY_EVERYONE,
            ]);
        }

        $stored = $this->geo->findLocation($viewer);
        if ($stored === null || $stored->located_at === null) {
            throw ValidationException::withMessages([
                'location' => ['Share your location to discover people and lives nearby.'],
            ]);
        }

        return $stored;
    }

    private function emptyPage(int $perPage): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            1,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }
}
