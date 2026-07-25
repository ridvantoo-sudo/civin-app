<?php

namespace App\Features\Games\Plugins\Ludo\Services;

use App\Features\Games\Plugins\Ludo\Repositories\Contracts\LudoRepository;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class LudoService
{
    public function __construct(private LudoRepository $repository) {}

    public function config(User $user): array
    {
        $this->ensureEligible($user);

        return $this->repository->config()?->toArray() ?? [];
    }

    public function history(User $user, int $perPage = 30)
    {
        $this->ensureEligible($user);

        return $this->repository->history($user, $perPage);
    }

    public function leaderboard(string $period = 'daily', int $limit = 50, ?string $mode = null)
    {
        return $this->repository->leaderboard($period, $limit, $mode);
    }

    public function statistics(User $user): array
    {
        $this->ensureEligible($user);

        return $this->repository->statistics($user);
    }

    private function ensureEligible(User $user): void
    {
        if ($user->status !== User::STATUS_ACTIVE || $user->is_guest) {
            throw new AuthorizationException('Not eligible to play Ludo.');
        }
    }
}
