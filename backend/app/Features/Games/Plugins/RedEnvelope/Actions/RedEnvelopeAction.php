<?php

namespace App\Features\Games\Plugins\RedEnvelope\Actions;

use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Models\RedEnvelopeClaim;
use App\Features\Games\Plugins\RedEnvelope\DTOs\ClaimRedEnvelopeDTO;
use App\Features\Games\Plugins\RedEnvelope\DTOs\CreateRedEnvelopeDTO;
use App\Features\Games\Plugins\RedEnvelope\Services\RedEnvelopeService;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class RedEnvelopeAction
{
    public function __construct(private RedEnvelopeService $service) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        return $this->service->config($user);
    }

    /** @return array{envelope: RedEnvelope, wallet: Wallet, replay: bool} */
    public function create(User $user, CreateRedEnvelopeDTO $data): array
    {
        return $this->service->create($user, $data);
    }

    /** @return array{envelope: RedEnvelope, claim: RedEnvelopeClaim, wallet: Wallet, replay: bool} */
    public function claim(User $user, ClaimRedEnvelopeDTO $data): array
    {
        return $this->service->claim($user, $data);
    }

    public function history(User $user, string $type = 'all', int $perPage = 30): LengthAwarePaginator
    {
        return $this->service->history($user, $type, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', string $type = 'winners', int $limit = 50): Collection
    {
        return $this->service->leaderboard($period, $type, $limit);
    }
}
