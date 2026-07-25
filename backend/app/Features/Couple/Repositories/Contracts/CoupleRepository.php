<?php

namespace App\Features\Couple\Repositories\Contracts;

use App\Features\Couple\DTOs\SendCoupleGiftData;
use App\Features\Couple\DTOs\SendProposalData;
use App\Features\Couple\DTOs\UpdateCoupleProfileData;
use App\Features\Couple\Models\Couple;
use App\Features\Couple\Models\CoupleGift;
use App\Features\Couple\Models\CoupleProposal;
use App\Features\Couple\Models\CoupleTaskProgress;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CoupleRepository
{
    public function find(string $coupleId): ?Couple;

    public function findActiveForUser(User|string $user): ?Couple;

    public function propose(User $from, SendProposalData $data): CoupleProposal;

    public function accept(CoupleProposal $proposal, User $actor): Couple;

    public function reject(CoupleProposal $proposal, User $actor): CoupleProposal;

    public function cancel(CoupleProposal $proposal, User $actor): CoupleProposal;

    /** @return Collection<int, CoupleProposal> */
    public function listProposalsForUser(User $user): Collection;

    public function updateProfile(Couple $couple, User $actor, UpdateCoupleProfileData $data): Couple;

    public function end(Couple $couple, User $actor): Couple;

    public function rankings(int $perPage): LengthAwarePaginator;

    public function sendGift(Couple $couple, User $sender, SendCoupleGiftData $data): CoupleGift;

    public function listGifts(Couple $couple, int $perPage): LengthAwarePaginator;

    /** @return Collection<int, array<string, mixed>> */
    public function listTasks(Couple $couple): Collection;

    public function claimTask(Couple $couple, User $actor, string $taskId): CoupleTaskProgress;

    public function checkIn(Couple $couple, User $actor): Couple;

    /** @return array<string, mixed> */
    public function anniversaryInfo(Couple $couple): array;

    public function claimAnniversaryBonus(Couple $couple, User $actor): Couple;
}
