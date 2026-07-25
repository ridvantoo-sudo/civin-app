<?php

namespace App\Features\GeoDiscovery\Repositories\Contracts;

use App\Features\GeoDiscovery\DTOs\GeoCriteria;
use App\Features\GeoDiscovery\Models\UserLocation;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface GeoDiscoveryRepository
{
    public function upsertLocation(
        User $user,
        float $latitude,
        float $longitude,
        string $geohash,
        int $precision,
        ?array $privacy = null,
    ): UserLocation;

    public function findLocation(User $user): ?UserLocation;

    public function updatePrivacy(User $user, array $privacy): UserLocation;

    public function nearbyUsers(User $viewer, UserLocation $origin, GeoCriteria $criteria): LengthAwarePaginator;

    public function nearbyLive(User $viewer, UserLocation $origin, GeoCriteria $criteria): LengthAwarePaginator;

    public function countryLive(User $viewer, string $country, GeoCriteria $criteria): LengthAwarePaginator;

    public function countryVoiceRooms(User $viewer, string $country, GeoCriteria $criteria): LengthAwarePaginator;

    public function countryRankings(
        User $viewer,
        string $country,
        string $type,
        string $period,
        int $limit,
    ): Collection;

    /** @return list<string> */
    public function excludeBlockedUserIds(User $viewer): array;
}
