<?php

namespace App\Features\FamilyClub\Services;

use App\Features\FamilyClub\DTOs\ContributeWalletData;
use App\Features\FamilyClub\DTOs\CreateFamilyData;
use App\Features\FamilyClub\DTOs\InviteMemberData;
use App\Features\FamilyClub\DTOs\JoinFamilyData;
use App\Features\FamilyClub\DTOs\ReviewMemberData;
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
use App\Features\FamilyClub\Repositories\Contracts\FamilyRepository;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class FamilyService
{
    private const CREATE_RATE_KEY = 'family-create:%s';

    private const ACTION_RATE_KEY = 'family-action:%s';

    private const RATE_MAX = 20;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(private FamilyRepository $families) {}

    public function show(Family $family): Family
    {
        return $this->families->find((string) $family->getKey())
            ?? $family->loadMissing(['owner.profile', 'owner.socialStatus']);
    }

    public function mine(User $user): ?Family
    {
        $membership = $this->families->findApprovedMembershipForUser($user);

        return $membership?->family !== null
            ? $this->families->find((string) $membership->family_id)
            : null;
    }

    public function create(User $owner, CreateFamilyData $data): Family
    {
        $this->ensureEligible($owner);
        $this->enforceRateLimit(sprintf(self::CREATE_RATE_KEY, $owner->getKey()), 'family');

        return $this->families->create($owner, $data, $this->uniqueSlug($data->name));
    }

    public function invite(Family $family, User $actor, InviteMemberData $data): FamilyInvite
    {
        $this->ensureEligible($actor);
        $this->enforceRateLimit(sprintf(self::ACTION_RATE_KEY, $family->getKey()), 'family');

        return $this->families->createInvite($family, $actor, $data);
    }

    public function join(User $user, JoinFamilyData $data): FamilyMember
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::ACTION_RATE_KEY, $user->getKey()), 'family');

        return $this->families->joinByCode($user, $data);
    }

    public function approve(Family $family, User $reviewer, ReviewMemberData $data): FamilyMember
    {
        $this->ensureEligible($reviewer);
        $applicant = User::query()->find($data->userId);
        if ($applicant === null) {
            throw ValidationException::withMessages(['user_id' => 'The selected user is invalid.']);
        }

        return $this->families->approve($family, $applicant, $reviewer);
    }

    public function reject(Family $family, User $reviewer, ReviewMemberData $data): FamilyMember
    {
        $this->ensureEligible($reviewer);
        $applicant = User::query()->find($data->userId);
        if ($applicant === null) {
            throw ValidationException::withMessages(['user_id' => 'The selected user is invalid.']);
        }

        return $this->families->reject($family, $applicant, $reviewer);
    }

    public function removeMember(Family $family, User $actor, User $memberUser): FamilyMember
    {
        $this->ensureEligible($actor);

        return $this->families->removeMember($family, $memberUser, $actor);
    }

    public function updateRole(Family $family, User $actor, UpdateMemberRoleData $data): FamilyMember
    {
        $this->ensureEligible($actor);

        return $this->families->updateRole($family, $actor, $data);
    }

    public function members(Family $family): Collection
    {
        return $this->families->listMembers($family);
    }

    public function rankings(int $perPage = 20): LengthAwarePaginator
    {
        return $this->families->rankings(max(1, min($perPage, 100)));
    }

    public function sendMessage(Family $family, User $user, SendFamilyMessageData $data): FamilyMessage
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::ACTION_RATE_KEY, $user->getKey()), 'family');

        return $this->families->sendMessage($family, $user, $data);
    }

    public function messages(Family $family, int $perPage = 30): LengthAwarePaginator
    {
        return $this->families->listMessages($family, max(1, min($perPage, 100)));
    }

    public function sendGift(Family $family, User $sender, SendFamilyGiftData $data): FamilyGift
    {
        $this->ensureEligible($sender);
        $this->enforceRateLimit(sprintf(self::ACTION_RATE_KEY, $sender->getKey()), 'family');

        return $this->families->sendGift($family, $sender, $data);
    }

    public function gifts(Family $family, int $perPage = 20): LengthAwarePaginator
    {
        return $this->families->listGifts($family, max(1, min($perPage, 100)));
    }

    public function contribute(Family $family, User $user, ContributeWalletData $data): FamilyWalletTransaction
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::ACTION_RATE_KEY, $user->getKey()), 'family');

        return $this->families->contribute($family, $user, $data);
    }

    public function walletTransactions(Family $family, int $perPage = 20): LengthAwarePaginator
    {
        return $this->families->listWalletTransactions($family, max(1, min($perPage, 100)));
    }

    public function missions(Family $family): Collection
    {
        return $this->families->listMissions($family);
    }

    public function claimMission(Family $family, User $actor, string $missionId): FamilyMissionProgress
    {
        $this->ensureEligible($actor);

        return $this->families->claimMission($family, $actor, $missionId);
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use Family Club.');
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'family';
        }

        $slug = $base;
        $suffix = 0;

        while (Family::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function enforceRateLimit(string $key, string $action): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                $action => 'You are performing family actions too quickly.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
