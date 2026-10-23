<?php

namespace App\Features\Agency\Repositories\Contracts;

use App\Features\Agency\DTOs\ApplyAgencyData;
use App\Features\Agency\DTOs\CreateAgencyData;
use App\Features\Agency\Models\Agency;
use App\Features\Agency\Models\AgencyCommission;
use App\Features\Agency\Models\AgencyMember;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AgencyRepository
{
    public function find(string $agencyId): ?Agency;

    public function findOwnedBy(User $user): ?Agency;

    public function findApprovedMembershipForUser(User|string $user): ?AgencyMember;

    public function create(User $owner, CreateAgencyData $data, string $slug): Agency;

    public function apply(Agency $agency, User $user, ApplyAgencyData $data): AgencyMember;

    public function approve(Agency $agency, User $applicant, User $reviewer): AgencyMember;

    public function reject(Agency $agency, User $applicant, User $reviewer): AgencyMember;

    public function removeMember(Agency $agency, User $memberUser, User $actor): AgencyMember;

    /** @return Collection<int, AgencyMember> */
    public function approvedHosts(Agency $agency): Collection;

    public function earnings(Agency $agency, int $perPage): LengthAwarePaginator;

    public function createCommissionFromGift(GiftTransaction $transaction): ?AgencyCommission;
}
