<?php

namespace App\Features\Games\Plugins\Quiz\Services;

use App\Features\Games\Plugins\Quiz\Events\TournamentFinished;
use App\Features\Games\Plugins\Quiz\Events\TournamentStarted;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Models\QuizTournament;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final readonly class QuizTournamentService
{
    public function list(int $perPage = 30): LengthAwarePaginator
    {
        return QuizTournament::query()
            ->whereIn('status', [
                QuizTournament::STATUS_SCHEDULED,
                QuizTournament::STATUS_ACTIVE,
                QuizTournament::STATUS_FINISHED,
            ])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END")
            ->orderBy('scheduled_at')
            ->paginate($perPage);
    }

    /**
     * Start scheduled tournaments whose scheduled_at has passed, and finish
     * active tournaments with no remaining active sessions.
     *
     * @return array{started: int, finished: int}
     */
    public function processDue(int $limit = 100): array
    {
        $started = $this->startScheduled($limit);
        $finished = $this->finishCompleted($limit);

        return ['started' => $started, 'finished' => $finished];
    }

    public function startScheduled(int $limit = 100): int
    {
        $count = 0;
        $due = QuizTournament::query()
            ->where('status', QuizTournament::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        foreach ($due as $tournament) {
            $updated = DB::transaction(function () use ($tournament): ?QuizTournament {
                $locked = QuizTournament::query()->whereKey($tournament->getKey())->lockForUpdate()->first();
                if ($locked === null || $locked->status !== QuizTournament::STATUS_SCHEDULED) {
                    return null;
                }

                $locked->fill([
                    'status' => QuizTournament::STATUS_ACTIVE,
                    'started_at' => now(),
                    'current_round' => max(1, (int) $locked->current_round),
                ])->save();

                return $locked->fresh();
            });

            if ($updated !== null) {
                TournamentStarted::dispatch($updated);
                $count++;
            }
        }

        return $count;
    }

    public function finishCompleted(int $limit = 100): int
    {
        $count = 0;
        $active = QuizTournament::query()
            ->where('status', QuizTournament::STATUS_ACTIVE)
            ->orderBy('started_at')
            ->limit($limit)
            ->get();

        foreach ($active as $tournament) {
            $hasOpenSessions = QuizSession::query()
                ->where('tournament_id', $tournament->getKey())
                ->whereNotIn('status', [
                    QuizSession::STATUS_FINISHED,
                    QuizSession::STATUS_CANCELLED,
                ])
                ->exists();

            if ($hasOpenSessions) {
                continue;
            }

            // Only auto-finish when at least one session exists and all are done,
            // or when the tournament has been active past a soft TTL with zero sessions.
            $sessionCount = QuizSession::query()
                ->where('tournament_id', $tournament->getKey())
                ->count();

            $staleEmpty = $sessionCount === 0
                && $tournament->started_at !== null
                && $tournament->started_at->lt(now()->subHours(24));

            if ($sessionCount === 0 && ! $staleEmpty) {
                continue;
            }

            $updated = DB::transaction(function () use ($tournament): ?QuizTournament {
                $locked = QuizTournament::query()->whereKey($tournament->getKey())->lockForUpdate()->first();
                if ($locked === null || $locked->status !== QuizTournament::STATUS_ACTIVE) {
                    return null;
                }

                $locked->fill([
                    'status' => QuizTournament::STATUS_FINISHED,
                    'finished_at' => now(),
                ])->save();

                return $locked->fresh();
            });

            if ($updated !== null) {
                TournamentFinished::dispatch($updated);
                $count++;
            }
        }

        return $count;
    }
}
