<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Actions;

use App\Features\Games\Plugins\RockPaperScissors\DTOs\AcceptRpsMatchDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\CreateRpsMatchDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\JoinRpsTournamentDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\RematchRpsDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\SubmitRpsMoveDTO;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMove;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsRound;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsTournament;
use App\Features\Games\Plugins\RockPaperScissors\Services\RpsService;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class RpsAction
{
    public function __construct(private RpsService $service) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        return $this->service->config($user);
    }

    /** @return array{match: RpsMatch, wallet: Wallet, replay: bool, matched: bool} */
    public function create(User $user, CreateRpsMatchDTO $data): array
    {
        return $this->service->create($user, $data);
    }

    /** @return array{match: RpsMatch, wallet: Wallet, replay: bool} */
    public function accept(User $user, AcceptRpsMatchDTO $data): array
    {
        return $this->service->accept($user, $data);
    }

    /** @return array{match: RpsMatch, round: RpsRound, move: RpsMove, replay: bool, resolved: bool, finished: bool} */
    public function move(User $user, SubmitRpsMoveDTO $data): array
    {
        return $this->service->move($user, $data);
    }

    /** @return array{match: RpsMatch, wallet: Wallet, replay: bool} */
    public function rematch(User $user, RematchRpsDTO $data): array
    {
        return $this->service->rematch($user, $data);
    }

    /** @return array{tournament: RpsTournament, wallet: Wallet, replay: bool} */
    public function joinTournament(User $user, JoinRpsTournamentDTO $data): array
    {
        return $this->service->joinTournament($user, $data);
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return $this->service->history($user, $perPage);
    }

    public function leaderboard(string $period = 'daily', int $limit = 50, ?string $mode = null, ?string $roomId = null): Collection
    {
        return $this->service->leaderboard($period, $limit, $mode, $roomId);
    }

    /** @return array<string, mixed> */
    public function statistics(User $user): array
    {
        return $this->service->statistics($user);
    }

    public function tournaments(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return $this->service->tournaments($user, $perPage);
    }

    public function show(User $user, string $matchId): RpsMatch
    {
        return $this->service->show($user, $matchId);
    }
}
