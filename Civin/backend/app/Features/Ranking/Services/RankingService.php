<?php

namespace App\Features\Ranking\Services;

use App\Features\Ranking\DTOs\RankingQueryData;
use App\Features\Ranking\DTOs\RankingScoreData;
use App\Features\Ranking\Events\RankingsCalculated;
use App\Features\Ranking\Models\Ranking;
use App\Features\Ranking\Repositories\Contracts\RankingRepository;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final readonly class RankingService
{
    public function __construct(
        private RankingCalculatorService $calculator,
        private RankingCacheService $cache,
        private RankingRepository $rankings,
    ) {}

    /**
     * @return Collection<int, Ranking>
     */
    public function list(User $actor, RankingQueryData $query): Collection
    {
        if ($actor->getKey() === null) {
            throw new AuthorizationException('Authentication is required to view rankings.');
        }

        if (! in_array($query->type, Ranking::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Unsupported ranking type.']);
        }

        if (! in_array($query->period, Ranking::PERIODS, true)) {
            throw ValidationException::withMessages(['period' => 'Unsupported ranking period.']);
        }

        /** @var array<int, array{user_id: string, score: int, rank: int}> $payload */
        $payload = $this->cache->remember($query, function () use ($query): array {
            $date = $this->calculator->periodDate($query->period);
            $stored = $this->rankings->listStored($query, $date);

            if ($stored->isNotEmpty()) {
                return $stored->map(fn (Ranking $ranking): array => [
                    'user_id' => (string) $ranking->user_id,
                    'score' => (int) $ranking->score,
                    'rank' => (int) $ranking->rank,
                ])->all();
            }

            return $this->calculator->calculate($query)
                ->map(fn (RankingScoreData $score): array => $score->toArray())
                ->all();
        });

        return $this->hydrateFromScores(
            collect($payload)->map(fn (array $row): RankingScoreData => new RankingScoreData(
                userId: (string) $row['user_id'],
                score: (int) $row['score'],
                rank: (int) $row['rank'],
            )),
        );
    }

    /**
     * @return Collection<int, RankingScoreData>
     */
    public function recalculate(string $type, string $period): Collection
    {
        $scores = $this->calculator->calculateAndPersist($type, $period);

        $this->cache->flushType($type, $period);

        RankingsCalculated::dispatch($type, $period, $scores->count());

        return $scores;
    }

    /**
     * @return array<int, string>
     */
    public function recalculatePeriod(string $period): array
    {
        $types = [];

        foreach (Ranking::TYPES as $type) {
            $this->recalculate($type, $period);
            $types[] = $type;
        }

        return $types;
    }

    /**
     * @param  Collection<int, RankingScoreData>  $scores
     * @return Collection<int, Ranking>
     */
    private function hydrateFromScores(Collection $scores): Collection
    {
        if ($scores->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->whereIn('id', $scores->map(fn (RankingScoreData $score): string => $score->userId)->all())
            ->with(['profile.country', 'socialStatus'])
            ->get()
            ->keyBy(fn (User $user): string => (string) $user->getKey());

        return $scores->map(function (RankingScoreData $score) use ($users): ?Ranking {
            $user = $users->get($score->userId);
            if ($user === null) {
                return null;
            }

            $ranking = new Ranking([
                'user_id' => $score->userId,
                'score' => $score->score,
                'rank' => $score->rank,
            ]);
            $ranking->setRelation('user', $user);

            return $ranking;
        })->filter()->values();
    }
}
