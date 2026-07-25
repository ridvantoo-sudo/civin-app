<?php

namespace App\Features\FamilyClub\Repositories\Contracts;

use App\Features\FamilyClub\DTOs\ContributeWalletData;
use App\Features\FamilyClub\DTOs\CreateFamilyData;
use App\Features\FamilyClub\DTOs\InviteMemberData;
use App\Features\FamilyClub\DTOs\JoinFamilyData;
use App\Features\FamilyClub\DTOs\SendFamilyGiftData;
use App\Features\FamilyClub\DTOs\SendFamilyMessageData;
use App\Features\FamilyClub\DTOs\UpdateMemberRoleData;
use App\Features\FamilyClub\Models\Family;
use App\Features\FamilyClub\Models\FamilyGift;
use App\Features\FamilyClub\Models\FamilyInvite;
use App\Features\FamilyClub\Models\FamilyMember;
use App\Features\FamilyClub\Models\FamilyMessage;
use App\Features\FamilyClub\Models\FamilyMissionProgress;
use App\Features\FamilyClub\Models\FamilyWalletTransaction;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface FamilyRepository
{
    public function find(string $familyId): ?Family;

    public function findOwnedBy(User $user): ?Family;

    public function findApprovedMembershipForUser(User|string $user): ?FamilyMember;

    public function create(User $owner, CreateFamilyData $data, string $slug): Family;

    public function createInvite(Family $family, User $actor, InviteMemberData $data): FamilyInvite;

    public function joinByCode(User $user, JoinFamilyData $data): FamilyMember;

    public function approve(Family $family, User $applicant, User $reviewer): FamilyMember;

    public function reject(Family $family, User $applicant, User $reviewer): FamilyMember;

    public function removeMember(Family $family, User $memberUser, User $actor): FamilyMember;

    public function updateRole(Family $family, User $actor, UpdateMemberRoleData $data): FamilyMember;

    /** @return Collection<int, FamilyMember> */
    public function listMembers(Family $family): Collection;

    public function rankings(int $perPage): LengthAwarePaginator;

    public function sendMessage(Family $family, User $user, SendFamilyMessageData $data): FamilyMessage;

    public function listMessages(Family $family, int $perPage): LengthAwarePaginator;

    public function sendGift(Family $family, User $sender, SendFamilyGiftData $data): FamilyGift;

    public function listGifts(Family $family, int $perPage): LengthAwarePaginator;

    public function contribute(Family $family, User $user, ContributeWalletData $data): FamilyWalletTransaction;

    public function listWalletTransactions(Family $family, int $perPage): LengthAwarePaginator;

    /** @return Collection<int, array<string, mixed>> */
    public function listMissions(Family $family): Collection;

    public function claimMission(Family $family, User $actor, string $missionId): FamilyMissionProgress;
}
