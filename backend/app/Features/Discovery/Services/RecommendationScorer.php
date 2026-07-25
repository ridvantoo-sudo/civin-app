<?php

namespace App\Features\Discovery\Services;

use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

/**
 * Lightweight content-based recommender for hosts/users.
 *
 * Score = country match + social graph + presence + popularity + freshness.
 */
final class RecommendationScorer
{
    /**
     * @param  Collection<int, User>  $candidates
     * @param  array<int, string>  $followedIds
     * @return Collection<int, User>
     */
    public function rank(
        User $viewer,
        Collection $candidates,
        array $followedIds,
        ?string $viewerCountryId,
    ): Collection {
        $followedLookup = array_fill_keys($followedIds, true);

        return $candidates
            ->map(function (User $user) use ($followedLookup, $viewerCountryId): User {
                $score = 0.0;
                $profile = $user->profile;
                $status = $user->socialStatus;

                if ($viewerCountryId !== null && $profile?->country_id === $viewerCountryId) {
                    $score += 30;
                }

                if (isset($followedLookup[$user->getKey()])) {
                    $score += 40;
                }

                if ($status?->is_online) {
                    $score += 15;
                }

                if ($status?->is_live) {
                    $score += 25;
                }

                if ($profile?->is_svip) {
                    $score += 20;
                } elseif ($profile?->is_vip) {
                    $score += 10;
                }

                $followers = max(0, (int) ($profile?->followers_count ?? 0));
                $score += min(25, log(1 + $followers, 10) * 8);

                $level = max(1, (int) ($profile?->level ?? 1));
                $score += min(10, $level / 5);

                if ($user->relationLoaded('hostedLiveRooms') && $user->hostedLiveRooms->isNotEmpty()) {
                    $score += 12;
                    $latest = $user->hostedLiveRooms->first()?->started_at;
                    if ($latest !== null && $latest->greaterThan(now()->subDays(3))) {
                        $score += 8;
                    }
                }

                $user->setAttribute('recommendation_score', round($score, 2));

                return $user;
            })
            ->sortByDesc(fn (User $user): float => (float) $user->getAttribute('recommendation_score'))
            ->values();
    }
}
