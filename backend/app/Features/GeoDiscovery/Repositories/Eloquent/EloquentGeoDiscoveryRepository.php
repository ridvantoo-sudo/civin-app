<?php

namespace App\Features\GeoDiscovery\Repositories\Eloquent;

use App\Features\GeoDiscovery\DTOs\GeoCriteria;
use App\Features\GeoDiscovery\Models\UserLocation;
use App\Features\GeoDiscovery\Repositories\Contracts\GeoDiscoveryRepository;
use App\Features\GeoDiscovery\Services\DistanceCalculator;
use App\Features\GeoDiscovery\Services\GeoHashService;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Ranking\Models\Ranking;
use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentGeoDiscoveryRepository implements GeoDiscoveryRepository
{
    public function __construct(
        private readonly GeoHashService $geoHash,
        private readonly DistanceCalculator $distance,
    ) {}

    public function upsertLocation(
        User $user,
        float $latitude,
        float $longitude,
        string $geohash,
        int $precision,
        ?array $privacy = null,
    ): UserLocation {
        $attributes = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geohash' => $geohash,
            'geohash_precision' => $precision,
            'located_at' => now(),
        ];

        if ($privacy !== null) {
            $attributes = array_merge($attributes, array_intersect_key($privacy, array_flip([
                'share_location',
                'show_distance',
                'nearby_visibility',
            ])));
        }

        return UserLocation::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            $attributes,
        );
    }

    public function findLocation(User $user): ?UserLocation
    {
        return UserLocation::query()->where('user_id', $user->getKey())->first();
    }

    public function updatePrivacy(User $user, array $privacy): UserLocation
    {
        $location = $this->findLocation($user);
        if ($location === null) {
            $location = new UserLocation([
                'user_id' => $user->getKey(),
                'latitude' => 0,
                'longitude' => 0,
                'geohash' => 's00000',
                'geohash_precision' => 6,
                'located_at' => null,
            ]);
        }

        $location->fill(array_intersect_key($privacy, array_flip([
            'share_location',
            'show_distance',
            'nearby_visibility',
        ])));
        $location->save();

        return $location->refresh();
    }

    public function nearbyUsers(User $viewer, UserLocation $origin, GeoCriteria $criteria): LengthAwarePaginator
    {
        $precision = $this->geoHash->precisionForRadiusKm($criteria->radiusKm);
        $prefix = substr($origin->geohash, 0, $precision);
        $prefixes = $this->geoHash->neighbours($prefix);
        $blocked = $this->excludeBlockedUserIds($viewer);
        $viewerFollows = $this->acceptedFollowingIds($viewer);

        $candidates = UserLocation::query()
            ->where('share_location', true)
            ->whereNotNull('located_at')
            ->where('nearby_visibility', '!=', UserLocation::VISIBILITY_NOBODY)
            ->where('user_id', '!=', $viewer->getKey())
            ->whereNotIn('user_id', $blocked)
            ->where(function (Builder $query) use ($prefixes): void {
                foreach ($prefixes as $index => $hash) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}('geohash', 'like', $hash.'%');
                }
            })
            ->with([
                'user.profile.country',
                'user.socialStatus',
            ])
            ->whereHas('user', fn (Builder $user) => $user
                ->where('status', User::STATUS_ACTIVE)
                ->when($criteria->country, fn (Builder $q, string $country) => $q
                    ->whereHas('profile.country', fn (Builder $c) => $this->applyCountryMatch($c, $country))))
            ->limit(max($criteria->perPage * 8, 80))
            ->get();

        $ranked = $candidates
            ->filter(function (UserLocation $location) use ($origin, $criteria, $viewerFollows): bool {
                if (! $this->distance->withinRadius(
                    $origin->latitude,
                    $origin->longitude,
                    $location->latitude,
                    $location->longitude,
                    $criteria->radiusKm,
                )) {
                    return false;
                }

                // "followers" = only people who follow the owner (viewer follows them).
                if ($location->nearby_visibility === UserLocation::VISIBILITY_FOLLOWERS) {
                    return in_array($location->user_id, $viewerFollows, true);
                }

                return true;
            })
            ->map(function (UserLocation $location) use ($origin): User {
                $user = $location->user;
                $km = $this->distance->haversineKm(
                    $origin->latitude,
                    $origin->longitude,
                    $location->latitude,
                    $location->longitude,
                );
                $user->setAttribute('distance_km', $location->show_distance
                    ? $this->distance->formatKm($km)
                    : null);
                $user->setAttribute('geohash_prefix', substr($location->geohash, 0, 5));

                return $user;
            })
            ->sortBy(fn (User $user) => $user->getAttribute('distance_km') ?? PHP_FLOAT_MAX)
            ->values();

        return $this->paginateCollection($ranked, $criteria->perPage);
    }

    public function nearbyLive(User $viewer, UserLocation $origin, GeoCriteria $criteria): LengthAwarePaginator
    {
        $precision = $this->geoHash->precisionForRadiusKm($criteria->radiusKm);
        $prefix = substr($origin->geohash, 0, $precision);
        $prefixes = $this->geoHash->neighbours($prefix);
        $blocked = $this->excludeBlockedUserIds($viewer);

        $rooms = LiveRoom::query()
            ->where('status', 'live')
            ->whereNotIn('host_id', $blocked)
            ->whereHas('host.location', function (Builder $query) use ($prefixes): void {
                $query->where('share_location', true)
                    ->whereNotNull('located_at')
                    ->where('nearby_visibility', '!=', UserLocation::VISIBILITY_NOBODY)
                    ->where(function (Builder $geo) use ($prefixes): void {
                        foreach ($prefixes as $index => $hash) {
                            $method = $index === 0 ? 'where' : 'orWhere';
                            $geo->{$method}('geohash', 'like', $hash.'%');
                        }
                    });
            })
            ->with(['host.profile.country', 'host.socialStatus', 'host.location', 'category'])
            ->orderByDesc('viewer_count')
            ->limit(max($criteria->perPage * 5, 50))
            ->get()
            ->filter(function (LiveRoom $room) use ($origin, $criteria): bool {
                $location = $room->host?->location;
                if ($location === null) {
                    return false;
                }

                return $this->distance->withinRadius(
                    $origin->latitude,
                    $origin->longitude,
                    $location->latitude,
                    $location->longitude,
                    $criteria->radiusKm,
                );
            })
            ->map(function (LiveRoom $room) use ($origin): LiveRoom {
                $location = $room->host->location;
                $km = $this->distance->haversineKm(
                    $origin->latitude,
                    $origin->longitude,
                    $location->latitude,
                    $location->longitude,
                );
                $room->setAttribute('distance_km', $location->show_distance
                    ? $this->distance->formatKm($km)
                    : null);

                return $room;
            })
            ->sortBy(fn (LiveRoom $room) => $room->getAttribute('distance_km') ?? PHP_FLOAT_MAX)
            ->values();

        return $this->paginateCollection($rooms, $criteria->perPage);
    }

    public function countryLive(User $viewer, string $country, GeoCriteria $criteria): LengthAwarePaginator
    {
        return LiveRoom::query()
            ->where('status', 'live')
            ->whereNotIn('host_id', $this->excludeBlockedUserIds($viewer))
            ->whereHas('host.profile.country', fn (Builder $q) => $this->applyCountryMatch($q, $country))
            ->with(['host.profile.country', 'host.socialStatus', 'category'])
            ->orderByDesc('viewer_count')
            ->orderByDesc('started_at')
            ->paginate($criteria->perPage);
    }

    public function countryVoiceRooms(User $viewer, string $country, GeoCriteria $criteria): LengthAwarePaginator
    {
        return VoiceRoom::query()
            ->where('status', VoiceRoom::STATUS_LIVE)
            ->whereNotIn('host_id', $this->excludeBlockedUserIds($viewer))
            ->whereHas('host.profile.country', fn (Builder $q) => $this->applyCountryMatch($q, $country))
            ->with(['host.profile.country', 'host.socialStatus'])
            ->orderByDesc('participant_count')
            ->orderByDesc('started_at')
            ->paginate($criteria->perPage);
    }

    public function countryRankings(
        User $viewer,
        string $country,
        string $type,
        string $period,
        int $limit,
    ): Collection {
        return Ranking::query()
            ->where('type', $type)
            ->where('period', $period)
            ->whereDate('date', now()->toDateString())
            ->whereHas('user.profile.country', fn (Builder $q) => $this->applyCountryMatch($q, $country))
            ->whereNotIn('user_id', $this->excludeBlockedUserIds($viewer))
            ->with(['user.profile.country', 'user.socialStatus'])
            ->orderBy('rank')
            ->limit($limit)
            ->get();
    }

    public function excludeBlockedUserIds(User $viewer): array
    {
        return DB::table('blocks')
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query
                ->where('blocker_id', $viewer->getKey())
                ->orWhere('blocked_id', $viewer->getKey()))
            ->get(['blocker_id', 'blocked_id'])
            ->flatMap(fn ($row): array => [
                $row->blocker_id === $viewer->getKey() ? $row->blocked_id : $row->blocker_id,
            ])
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function acceptedFollowingIds(User $viewer): array
    {
        return $viewer->following()
            ->where('status', 'accepted')
            ->pluck('followed_id')
            ->all();
    }

    private function applyCountryMatch(Builder $countryQuery, string $country): void
    {
        $countryQuery->where(function (Builder $match) use ($country): void {
            $match->where('id', $country)
                ->orWhere('alpha2', strtoupper($country))
                ->orWhere('alpha3', strtoupper($country))
                ->orWhere('name', 'like', addcslashes($country, '%_\\').'%');
        });
    }

    private function paginateCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) request()->integer('page', 1));
        $slice = $items->forPage($page, $perPage)->values();

        return new Paginator(
            $slice,
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }
}
