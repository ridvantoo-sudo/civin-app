<?php

namespace App\Features\Ranking\Repositories\Eloquent;

use App\Features\Followers\Models\Follow;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\Profiles\Models\Profile;
use App\Features\Ranking\DTOs\RankingQueryData;
use App\Features\Ranking\DTOs\RankingScoreData;
use App\Features\Ranking\Models\Ranking;
use App\Features\Ranking\Models\RankingSnapshot;
use App\Features\Ranking\Repositories\Contracts\RankingRepository;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Models\VoiceSession;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentRankingRepository implements RankingRepository
{
    public function replacePeriodRankings(
        string $type,
        string $period,
        CarbonInterface $date,
        Collection $scores,
    ): void {
        DB::transaction(function () use ($type, $period, $date, $scores): void {
            Ranking::query()
                ->where('type', $type)
                ->where('period', $period)
                ->whereDate('date', $date->toDateString())
                ->delete();

            $now = now();
            $rows = $scores->map(fn (RankingScoreData $score): array => [
                'id' => (string) Str::uuid(),
                'type' => $type,
                'period' => $period,
                'user_id' => $score->userId,
                'score' => $score->score,
                'rank' => $score->rank,
                'date' => $date->toDateString(),
                'created_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 100) as $chunk) {
                Ranking::query()->insert($chunk);
            }
        });
    }

    public function createSnapshot(string $type, string $period, array $data): RankingSnapshot
    {
        return RankingSnapshot::query()->create([
            'type' => $type,
            'period' => $period,
            'data' => $data,
            'created_at' => now(),
        ]);
    }

    public function listStored(RankingQueryData $query, CarbonInterface $date): Collection
    {
        return Ranking::query()
            ->where('type', $query->type)
            ->where('period', $query->period)
            ->whereDate('date', $date->toDateString())
            ->when($query->country, fn (Builder $builder, string $country) => $this->applyCountryFilter($builder, $country, 'user_id'))
            ->with(['user.profile.country', 'user.socialStatus'])
            ->orderBy('rank')
            ->limit($query->limit)
            ->get();
    }

    public function aggregateHostDiamonds(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection
    {
        $rows = GiftTransaction::query()
            ->selectRaw('receiver_id as user_id, SUM(coins) as score')
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<=', $to))
            ->when($country, fn (Builder $query, string $value) => $this->applyCountryFilter($query, $value, 'receiver_id'))
            ->groupBy('receiver_id')
            ->orderByDesc('score')
            ->orderBy('receiver_id')
            ->limit($limit)
            ->get();

        return $this->mapScores($rows);
    }

    public function aggregateLiveDuration(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection
    {
        $rows = DB::table('live_sessions')
            ->selectRaw('live_rooms.host_id as user_id, SUM(live_sessions.duration) as score')
            ->join('live_rooms', 'live_rooms.id', '=', 'live_sessions.room_id')
            ->when($from, fn ($query) => $query->where(function ($inner) use ($from): void {
                $inner->where('live_rooms.ended_at', '>=', $from)
                    ->orWhere(function ($created) use ($from): void {
                        $created->whereNull('live_rooms.ended_at')
                            ->where('live_sessions.created_at', '>=', $from);
                    });
            }))
            ->when($to, fn ($query) => $query->where(function ($inner) use ($to): void {
                $inner->where('live_rooms.ended_at', '<=', $to)
                    ->orWhere(function ($created) use ($to): void {
                        $created->whereNull('live_rooms.ended_at')
                            ->where('live_sessions.created_at', '<=', $to);
                    });
            }))
            ->when($country, function ($query, string $value) {
                $profileQuery = Profile::query()
                    ->select('user_id')
                    ->whereHas('country', function (Builder $countryQuery) use ($value): void {
                        $countryQuery
                            ->where('id', $value)
                            ->orWhere('alpha2', strtoupper($value))
                            ->orWhere('alpha3', strtoupper($value))
                            ->orWhere('name', 'like', addcslashes($value, '%_\\').'%');
                    });

                return $query->whereIn('live_rooms.host_id', $profileQuery);
            })
            ->where('live_rooms.status', 'ended')
            ->groupBy('live_rooms.host_id')
            ->orderByDesc('score')
            ->orderBy('live_rooms.host_id')
            ->limit($limit)
            ->get();

        return $this->mapScores($rows);
    }

    public function aggregateTopGifters(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection
    {
        $rows = GiftTransaction::query()
            ->selectRaw('sender_id as user_id, SUM(coins) as score')
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<=', $to))
            ->when($country, fn (Builder $query, string $value) => $this->applyCountryFilter($query, $value, 'sender_id'))
            ->groupBy('sender_id')
            ->orderByDesc('score')
            ->orderBy('sender_id')
            ->limit($limit)
            ->get();

        return $this->mapScores($rows);
    }

    public function aggregatePkWinners(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection
    {
        $rows = PkBattle::query()
            ->selectRaw('winner_id as user_id, COUNT(*) as score')
            ->where('status', PkBattle::STATUS_FINISHED)
            ->whereNotNull('winner_id')
            ->when($from, fn (Builder $query) => $query->where('ended_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('ended_at', '<=', $to))
            ->when($country, fn (Builder $query, string $value) => $this->applyCountryFilter($query, $value, 'winner_id'))
            ->groupBy('winner_id')
            ->orderByDesc('score')
            ->orderBy('winner_id')
            ->limit($limit)
            ->get();

        return $this->mapScores($rows);
    }

    public function aggregateVoiceHosts(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection
    {
        $rows = VoiceSession::query()
            ->selectRaw('voice_rooms.host_id as user_id, SUM(voice_sessions.duration) as score')
            ->join('voice_rooms', 'voice_rooms.id', '=', 'voice_sessions.room_id')
            ->when($from, fn (Builder $query) => $query->where(function (Builder $inner) use ($from): void {
                $inner->where('voice_rooms.ended_at', '>=', $from)
                    ->orWhere(function (Builder $live) use ($from): void {
                        $live->whereNull('voice_rooms.ended_at')
                            ->where('voice_sessions.created_at', '>=', $from);
                    });
            }))
            ->when($to, fn (Builder $query) => $query->where(function (Builder $inner) use ($to): void {
                $inner->where('voice_rooms.ended_at', '<=', $to)
                    ->orWhere(function (Builder $live) use ($to): void {
                        $live->whereNull('voice_rooms.ended_at')
                            ->where('voice_sessions.created_at', '<=', $to);
                    });
            }))
            ->when($country, fn (Builder $query, string $value) => $this->applyCountryFilter($query, $value, 'voice_rooms.host_id'))
            ->where('voice_rooms.status', VoiceRoom::STATUS_ENDED)
            ->groupBy('voice_rooms.host_id')
            ->orderByDesc('score')
            ->orderBy('voice_rooms.host_id')
            ->limit($limit)
            ->get();

        return $this->mapScores($rows);
    }

    public function aggregatePopularUsers(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection
    {
        if ($from === null && $to === null) {
            $rows = Profile::query()
                ->selectRaw('user_id, followers_count as score')
                ->where('followers_count', '>', 0)
                ->when($country, fn (Builder $query, string $value) => $this->applyCountryFilter($query, $value, 'user_id', false))
                ->orderByDesc('followers_count')
                ->orderBy('user_id')
                ->limit($limit)
                ->get();

            return $this->mapScores($rows);
        }

        $rows = Follow::query()
            ->selectRaw('followed_id as user_id, COUNT(*) as score')
            ->where('status', 'accepted')
            ->when($from, fn (Builder $query) => $query->where('accepted_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('accepted_at', '<=', $to))
            ->when($country, fn (Builder $query, string $value) => $this->applyCountryFilter($query, $value, 'followed_id'))
            ->groupBy('followed_id')
            ->orderByDesc('score')
            ->orderBy('followed_id')
            ->limit($limit)
            ->get();

        return $this->mapScores($rows);
    }

    private function applyCountryFilter(Builder $query, string $country, string $userColumn, bool $viaWhereIn = true): Builder
    {
        $normalized = trim($country);

        $profileQuery = Profile::query()
            ->select('user_id')
            ->whereHas('country', function (Builder $countryQuery) use ($normalized): void {
                $countryQuery
                    ->where('id', $normalized)
                    ->orWhere('alpha2', strtoupper($normalized))
                    ->orWhere('alpha3', strtoupper($normalized))
                    ->orWhere('name', 'like', addcslashes($normalized, '%_\\').'%');
            });

        if (! $viaWhereIn && $query->getModel() instanceof Profile) {
            return $query->whereHas('country', function (Builder $countryQuery) use ($normalized): void {
                $countryQuery
                    ->where('id', $normalized)
                    ->orWhere('alpha2', strtoupper($normalized))
                    ->orWhere('alpha3', strtoupper($normalized))
                    ->orWhere('name', 'like', addcslashes($normalized, '%_\\').'%');
            });
        }

        return $query->whereIn($userColumn, $profileQuery);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, RankingScoreData>
     */
    private function mapScores(Collection $rows): Collection
    {
        return $rows
            ->values()
            ->map(fn (object $row, int $index): RankingScoreData => new RankingScoreData(
                userId: (string) $row->user_id,
                score: (int) $row->score,
                rank: $index + 1,
            ));
    }
}
