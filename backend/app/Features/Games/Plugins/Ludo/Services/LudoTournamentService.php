<?php

namespace App\Features\Games\Plugins\Ludo\Services;

use App\Features\Games\Plugins\Ludo\DTOs\JoinLudoTournamentDTO;
use App\Features\Games\Plugins\Ludo\Models\LudoTournament;
use App\Features\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LudoTournamentService
{
    public function list(int $perPage = 30)
    {
        return LudoTournament::query()->latest('scheduled_at')->paginate($perPage);
    }

    public function join(User $user, JoinLudoTournamentDTO $dto): LudoTournament
    {
        return DB::transaction(function () use ($user, $dto): LudoTournament {
            $tournament = LudoTournament::query()->whereKey($dto->tournamentId)->lockForUpdate()->firstOrFail();
            if ($tournament->status !== 'registration') {
                throw ValidationException::withMessages(['tournament_id' => 'Registration is closed.']);
            }
            $ids = array_map('strval', $tournament->participant_ids ?? []);
            if (! in_array((string) $user->getKey(), $ids, true)) {
                if (count($ids) >= $tournament->max_players) {
                    throw ValidationException::withMessages(['tournament_id' => 'Tournament is full.']);
                }
                $ids[] = (string) $user->getKey();
                $tournament->update(['participant_ids' => $ids, 'players_count' => count($ids)]);
            }

            return $tournament->fresh();
        });
    }

    public function processDue(int $limit = 50): int
    {
        return LudoTournament::query()->where('status', 'registration')
            ->where('registration_closes_at', '<=', now())->limit($limit)
            ->update(['status' => 'scheduled']);
    }
}
