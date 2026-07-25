<?php

namespace App\Features\Discovery\Repositories\Eloquent;

use App\Features\Discovery\DTOs\DiscoveryCriteria;
use App\Features\Discovery\Repositories\Contracts\DiscoveryRepository;
use App\Features\Discovery\Services\RecommendationScorer;
use App\Features\LiveStreaming\Models\LiveCategory;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentDiscoveryRepository implements DiscoveryRepository
{
    public function __construct(private readonly RecommendationScorer $scorer) {}

    public function trendingLive(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return LiveRoom::query()
            ->where('status', 'live')
            ->whereNotIn('host_id', $this->excludeBlockedUserIds($viewer))
            ->when($criteria->categoryId, fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($criteria->country, fn (Builder $query, string $country) => $this->filterHostCountry($query, $country))
            ->with(['host.profile.country', 'host.socialStatus', 'category'])
            ->orderByDesc('viewer_count')
            ->orderByDesc('started_at')
            ->paginate($criteria->perPage);
    }

    public function trendingVoice(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return VoiceRoom::query()
            ->where('status', VoiceRoom::STATUS_LIVE)
            ->whereNotIn('host_id', $this->excludeBlockedUserIds($viewer))
            ->when($criteria->country, fn (Builder $query, string $country) => $this->filterHostCountry($query, $country))
            ->with(['host.profile.country', 'host.socialStatus'])
            ->orderByDesc('participant_count')
            ->orderByDesc('started_at')
            ->paginate($criteria->perPage);
    }

    public function popularHosts(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return $this->baseDiscoverableUsers($viewer)
            ->where(function (Builder $query): void {
                $query->whereHas('hostedLiveRooms')
                    ->orWhereExists(fn ($exists) => $exists
                        ->selectRaw('1')
                        ->from('voice_rooms')
                        ->whereColumn('voice_rooms.host_id', 'users.id'));
            })
            ->when($criteria->country, fn (Builder $query, string $country) => $query
                ->whereHas('profile.country', fn (Builder $countryQuery) => $this->applyCountryMatch($countryQuery, $country)))
            ->with('profile.country', 'socialStatus')
            ->orderByDesc(DB::raw('COALESCE((SELECT followers_count FROM profiles WHERE profiles.user_id = users.id LIMIT 1), 0)'))
            ->orderByDesc(DB::raw('COALESCE((SELECT likes_count FROM profiles WHERE profiles.user_id = users.id LIMIT 1), 0)'))
            ->orderBy('username')
            ->paginate($criteria->perPage);
    }

    public function nearby(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        $country = $criteria->country ?? $viewer->profile?->country_id;
        if ($country === null || $country === '') {
            return $this->emptyPaginator($criteria->perPage);
        }

        return $this->baseDiscoverableUsers($viewer)
            ->whereHas('profile.country', fn (Builder $countryQuery) => $this->applyCountryMatch($countryQuery, $country))
            ->whereHas('socialStatus', fn (Builder $status) => $status
                ->where(fn (Builder $presence) => $presence
                    ->where('is_online', true)
                    ->orWhere('is_live', true)))
            ->with('profile.country', 'socialStatus')
            ->orderByDesc(DB::raw('COALESCE((SELECT is_live FROM user_status WHERE user_status.user_id = users.id LIMIT 1), 0)'))
            ->orderByDesc(DB::raw('COALESCE((SELECT is_online FROM user_status WHERE user_status.user_id = users.id LIMIT 1), 0)'))
            ->orderBy('username')
            ->paginate($criteria->perPage);
    }

    public function newHosts(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        $since = now()->subDays(30)->toDateTimeString();

        $firstHosted = DB::query()
            ->fromSub(function ($query) use ($since): void {
                $query->from('live_rooms')
                    ->selectRaw('host_id, MIN(started_at) as first_hosted_at')
                    ->whereNotNull('started_at')
                    ->where('started_at', '>=', $since)
                    ->groupBy('host_id')
                    ->unionAll(
                        DB::table('voice_rooms')
                            ->selectRaw('host_id, MIN(started_at) as first_hosted_at')
                            ->whereNotNull('started_at')
                            ->where('started_at', '>=', $since)
                            ->groupBy('host_id')
                    );
            }, 'host_starts')
            ->selectRaw('host_id, MIN(first_hosted_at) as first_hosted_at')
            ->groupBy('host_id');

        return $this->baseDiscoverableUsers($viewer)
            ->joinSub($firstHosted, 'new_hosts', 'new_hosts.host_id', '=', 'users.id')
            ->when($criteria->country, fn (Builder $query, string $country) => $query
                ->whereHas('profile.country', fn (Builder $countryQuery) => $this->applyCountryMatch($countryQuery, $country)))
            ->with('profile.country', 'socialStatus')
            ->orderByDesc('new_hosts.first_hosted_at')
            ->select('users.*')
            ->paginate($criteria->perPage);
    }

    public function recommended(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        $blocked = $this->excludeBlockedUserIds($viewer);
        $followedIds = $viewer->following()
            ->where('status', 'accepted')
            ->pluck('followed_id')
            ->all();
        $viewerCountryId = $viewer->profile?->country_id;

        $candidates = $this->baseDiscoverableUsers($viewer)
            ->whereNotIn('users.id', $blocked)
            ->with([
                'profile.country',
                'socialStatus',
                'hostedLiveRooms' => fn ($query) => $query
                    ->where(function (Builder $rooms): void {
                        $rooms->where('status', 'live')
                            ->orWhere('started_at', '>=', now()->subDays(7));
                    })
                    ->latest('started_at')
                    ->limit(3),
            ])
            ->where(function (Builder $query) use ($viewerCountryId, $followedIds): void {
                $query->whereHas('socialStatus', fn (Builder $status) => $status
                    ->where('is_online', true)
                    ->orWhere('is_live', true));

                if ($viewerCountryId !== null) {
                    $query->orWhereHas('profile', fn (Builder $profile) => $profile->where('country_id', $viewerCountryId));
                }

                if ($followedIds !== []) {
                    $query->orWhereIn('users.id', $followedIds);
                }

                $query->orWhereHas('profile', fn (Builder $profile) => $profile->where('followers_count', '>', 0));
            })
            ->limit(max($criteria->perPage * 5, 50))
            ->get();

        $ranked = $this->scorer->rank($viewer, $candidates, $followedIds, $viewerCountryId);
        $page = max(1, (int) request()->integer('page', 1));
        $slice = $ranked->forPage($page, $criteria->perPage)->values();

        return new Paginator(
            $slice,
            $ranked->count(),
            $criteria->perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    public function onlineUsers(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        return $this->baseDiscoverableUsers($viewer)
            ->whereHas('socialStatus', fn (Builder $status) => $status->where('is_online', true))
            ->when($criteria->country, fn (Builder $query, string $country) => $query
                ->whereHas('profile.country', fn (Builder $countryQuery) => $this->applyCountryMatch($countryQuery, $country)))
            ->with('profile.country', 'socialStatus')
            ->orderByDesc(DB::raw('COALESCE((SELECT is_live FROM user_status WHERE user_status.user_id = users.id LIMIT 1), 0)'))
            ->orderByDesc(DB::raw('COALESCE((SELECT last_seen_at FROM user_status WHERE user_status.user_id = users.id LIMIT 1), users.created_at)'))
            ->orderBy('username')
            ->paginate($criteria->perPage);
    }

    public function search(User $viewer, DiscoveryCriteria $criteria): LengthAwarePaginator
    {
        $term = $criteria->query;
        $type = $criteria->type ?? 'all';

        if ($term === null || $term === '') {
            return $this->emptyPaginator($criteria->perPage);
        }

        if ($type === 'live') {
            return $this->searchLive($viewer, $term, $criteria->perPage);
        }

        if ($type === 'voice') {
            return $this->searchVoice($viewer, $term, $criteria->perPage);
        }

        // Default / users: people search (existing UserSearch semantics).
        return $this->baseDiscoverableUsers($viewer)
            ->when($term, function (Builder $query, string $value): void {
                $escaped = addcslashes($value, '%_\\');
                $query->where(function (Builder $search) use ($value, $escaped): void {
                    if (Str::isUuid($value)) {
                        $search->orWhere('users.id', $value);
                    }
                    $search->orWhere('username', 'like', "%{$escaped}%")
                        ->orWhereHas('profile', fn (Builder $profile) => $profile
                            ->where('display_name', 'like', "%{$escaped}%"));
                });
            })
            ->when($criteria->country, fn (Builder $query, string $country) => $query
                ->whereHas('profile.country', fn (Builder $countryQuery) => $this->applyCountryMatch($countryQuery, $country)))
            ->with('profile.country', 'socialStatus')
            ->orderBy('username')
            ->paginate($criteria->perPage);
    }

    public function categories(): Collection
    {
        return LiveCategory::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
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

    private function baseDiscoverableUsers(User $viewer): Builder
    {
        return User::query()
            ->where('users.id', '!=', $viewer->getKey())
            ->where('status', User::STATUS_ACTIVE)
            ->whereNotIn('users.id', $this->excludeBlockedUserIds($viewer));
    }

    private function searchLive(User $viewer, string $term, int $perPage): LengthAwarePaginator
    {
        $escaped = addcslashes($term, '%_\\');

        return LiveRoom::query()
            ->where('status', 'live')
            ->whereNotIn('host_id', $this->excludeBlockedUserIds($viewer))
            ->where(function (Builder $query) use ($escaped): void {
                $query->where('title', 'like', "%{$escaped}%")
                    ->orWhere('description', 'like', "%{$escaped}%")
                    ->orWhereHas('host', fn (Builder $host) => $host
                        ->where('username', 'like', "%{$escaped}%")
                        ->orWhereHas('profile', fn (Builder $profile) => $profile
                            ->where('display_name', 'like', "%{$escaped}%")));
            })
            ->with(['host.profile.country', 'host.socialStatus', 'category'])
            ->orderByDesc('viewer_count')
            ->paginate($perPage);
    }

    private function searchVoice(User $viewer, string $term, int $perPage): LengthAwarePaginator
    {
        $escaped = addcslashes($term, '%_\\');

        return VoiceRoom::query()
            ->where('status', VoiceRoom::STATUS_LIVE)
            ->whereNotIn('host_id', $this->excludeBlockedUserIds($viewer))
            ->where(function (Builder $query) use ($escaped): void {
                $query->where('title', 'like', "%{$escaped}%")
                    ->orWhere('description', 'like', "%{$escaped}%")
                    ->orWhereHas('host', fn (Builder $host) => $host
                        ->where('username', 'like', "%{$escaped}%")
                        ->orWhereHas('profile', fn (Builder $profile) => $profile
                            ->where('display_name', 'like', "%{$escaped}%")));
            })
            ->with(['host.profile.country', 'host.socialStatus'])
            ->orderByDesc('participant_count')
            ->paginate($perPage);
    }

    private function filterHostCountry(Builder $query, string $country): Builder
    {
        return $query->whereHas('host.profile.country', fn (Builder $countryQuery) => $this->applyCountryMatch($countryQuery, $country));
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

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new Paginator(
            collect(),
            0,
            $perPage,
            1,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }
}
