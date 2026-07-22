<?php

namespace App\Features\UserSearch\Repositories\Eloquent;

use App\Features\Users\Models\User;
use App\Features\UserSearch\DTOs\UserSearchCriteria;
use App\Features\UserSearch\Repositories\Contracts\UserSearchRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class EloquentUserSearchRepository implements UserSearchRepository
{
    public function search(User $viewer, UserSearchCriteria $criteria): LengthAwarePaginator
    {
        return User::query()
            ->where('users.id', '!=', $viewer->getKey())
            ->where('status', 'active')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('blocks')
                ->whereNull('blocks.deleted_at')
                ->where(fn ($blocked) => $blocked
                    ->where(fn ($pair) => $pair
                        ->where('blocks.blocker_id', $viewer->getKey())
                        ->whereColumn('blocks.blocked_id', 'users.id'))
                    ->orWhere(fn ($pair) => $pair
                        ->whereColumn('blocks.blocker_id', 'users.id')
                        ->where('blocks.blocked_id', $viewer->getKey()))))
            ->when($criteria->query, function (Builder $query, string $term): void {
                $escaped = addcslashes($term, '%_\\');
                $query->where(function (Builder $search) use ($term, $escaped): void {
                    if (Str::isUuid($term)) {
                        $search->orWhere('users.id', $term);
                    }
                    $search->orWhere('username', 'like', "%{$escaped}%")
                        ->orWhereHas('profile', fn (Builder $profile) => $profile
                            ->where('display_name', 'like', "%{$escaped}%"));
                });
            })
            ->when($criteria->country, fn (Builder $query, string $country) => $query
                ->whereHas('profile.country', fn (Builder $countryQuery) => $countryQuery
                    ->where('id', $country)
                    ->orWhere('alpha2', strtoupper($country))
                    ->orWhere('alpha3', strtoupper($country))
                    ->orWhere('name', 'like', addcslashes($country, '%_\\').'%')))
            ->when($criteria->isOnline !== null, function (Builder $query) use ($criteria): void {
                if ($criteria->isOnline) {
                    $query->whereHas('socialStatus', fn (Builder $status) => $status->where('is_online', true));
                } else {
                    $query->where(fn (Builder $offline) => $offline
                        ->whereDoesntHave('socialStatus')
                        ->orWhereHas('socialStatus', fn (Builder $status) => $status->where('is_online', false)));
                }
            })
            ->with('profile.country', 'socialStatus')
            ->orderBy('username')
            ->paginate($criteria->perPage);
    }
}
