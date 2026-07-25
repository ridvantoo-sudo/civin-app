<?php

namespace App\Features\Games\Plugins\Ludo\Actions;

use App\Features\Games\Plugins\Ludo\DTOs\CreateLudoMatchDTO;
use App\Features\Games\Plugins\Ludo\DTOs\JoinLudoMatchDTO;
use App\Features\Games\Plugins\Ludo\DTOs\JoinLudoTournamentDTO;
use App\Features\Games\Plugins\Ludo\DTOs\LeaveLudoDTO;
use App\Features\Games\Plugins\Ludo\DTOs\MovePieceDTO;
use App\Features\Games\Plugins\Ludo\DTOs\ReadyLudoDTO;
use App\Features\Games\Plugins\Ludo\DTOs\ReconnectLudoDTO;
use App\Features\Games\Plugins\Ludo\DTOs\RollDiceDTO;
use App\Features\Games\Plugins\Ludo\Repositories\Contracts\LudoRepository;
use App\Features\Games\Plugins\Ludo\Services\LudoMatchService;
use App\Features\Games\Plugins\Ludo\Services\LudoService;
use App\Features\Games\Plugins\Ludo\Services\LudoTournamentService;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class LudoAction
{
    public function __construct(
        private LudoService $ludo,
        private LudoMatchService $matches,
        private LudoTournamentService $tournaments,
        private LudoRepository $repository,
    ) {}

    public function config(User $user): array
    {
        return $this->ludo->config($user);
    }

    public function create(User $user, CreateLudoMatchDTO $dto): array
    {
        return $this->matches->create($user, $dto);
    }

    public function join(User $user, JoinLudoMatchDTO $dto): array
    {
        return $this->matches->join($user, $dto);
    }

    public function ready(User $user, ReadyLudoDTO $dto)
    {
        return $this->matches->ready($user, $dto);
    }

    public function roll(User $user, RollDiceDTO $dto): array
    {
        return $this->matches->roll($user, $dto);
    }

    public function move(User $user, MovePieceDTO $dto): array
    {
        return $this->matches->move($user, $dto);
    }

    public function reconnect(User $user, ReconnectLudoDTO $dto)
    {
        return $this->matches->reconnect($user, $dto);
    }

    public function leave(User $user, LeaveLudoDTO $dto)
    {
        return $this->matches->leave($user, $dto);
    }

    public function history(User $user, int $perPage = 30)
    {
        return $this->ludo->history($user, $perPage);
    }

    public function leaderboard(string $period, int $limit, ?string $mode = null)
    {
        return $this->ludo->leaderboard($period, $limit, $mode);
    }

    public function statistics(User $user): array
    {
        return $this->ludo->statistics($user);
    }

    public function tournaments(int $perPage = 30)
    {
        return $this->tournaments->list($perPage);
    }

    public function joinTournament(User $user, JoinLudoTournamentDTO $dto)
    {
        return $this->tournaments->join($user, $dto);
    }

    public function show(User $user, string $id)
    {
        $match = $this->repository->match($id);
        if (! $match || (! $match->spectators_enabled && ! $match->players->contains('user_id', $user->getKey()))) {
            throw new AuthorizationException('You may not view this match.');
        }

        return $match;
    }

    public function replays(User $user, int $perPage = 30)
    {
        return $this->repository->replays($user, $perPage);
    }
}
