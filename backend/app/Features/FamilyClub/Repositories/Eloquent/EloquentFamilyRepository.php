<?php

namespace App\Features\FamilyClub\Repositories\Eloquent;

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
use App\Features\FamilyClub\Models\FamilyMission;
use App\Features\FamilyClub\Models\FamilyMissionProgress;
use App\Features\FamilyClub\Models\FamilyWalletTransaction;
use App\Features\FamilyClub\Repositories\Contracts\FamilyRepository;
use App\Features\Gifts\Models\Gift;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EloquentFamilyRepository implements FamilyRepository
{
    public function __construct(private readonly WalletRepository $wallets) {}

    public function find(string $familyId): ?Family
    {
        return Family::query()
            ->with(['owner.profile', 'owner.socialStatus'])
            ->find($familyId);
    }

    public function findOwnedBy(User $user): ?Family
    {
        return Family::query()
            ->where('owner_id', $user->getKey())
            ->first();
    }

    public function findApprovedMembershipForUser(User|string $user): ?FamilyMember
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return FamilyMember::query()
            ->where('user_id', $userId)
            ->where('status', FamilyMember::STATUS_APPROVED)
            ->with('family')
            ->first();
    }

    public function create(User $owner, CreateFamilyData $data, string $slug): Family
    {
        return DB::transaction(function () use ($owner, $data, $slug): Family {
            $lockedOwner = User::query()->lockForUpdate()->findOrFail($owner->getKey());

            if ($this->findOwnedBy($lockedOwner) !== null) {
                throw ValidationException::withMessages([
                    'name' => 'You already own a family.',
                ]);
            }

            if ($this->findApprovedMembershipForUser($lockedOwner) !== null) {
                throw ValidationException::withMessages([
                    'name' => 'You already belong to a family.',
                ]);
            }

            $now = now();

            $family = Family::query()->create([
                'owner_id' => $lockedOwner->getKey(),
                'name' => $data->name,
                'slug' => $slug,
                'description' => $data->description,
                'logo' => $data->logo,
                'badge_key' => Family::BADGE_BRONZE,
                'badge_url' => null,
                'level' => 1,
                'xp' => 0,
                'xp_to_next_level' => Family::xpRequiredForLevel(1),
                'status' => Family::STATUS_ACTIVE,
                'members_count' => 1,
                'max_members' => 50,
                'ranking_score' => 0,
                'total_gifts_received' => 0,
                'total_gifts_sent' => 0,
                'wallet_coins' => 0,
                'wallet_diamonds' => 0,
            ]);

            FamilyMember::query()->create([
                'family_id' => $family->getKey(),
                'user_id' => $lockedOwner->getKey(),
                'role' => FamilyMember::ROLE_OWNER,
                'status' => FamilyMember::STATUS_APPROVED,
                'message' => null,
                'invited_at' => null,
                'joined_at' => $now,
                'reviewed_at' => $now,
                'invited_by' => null,
                'reviewed_by' => $lockedOwner->getKey(),
                'removed_at' => null,
                'contribution_xp' => 0,
                'contribution_gifts' => 0,
            ]);

            return $family->fresh(['owner.profile', 'owner.socialStatus']);
        });
    }

    public function createInvite(Family $family, User $actor, InviteMemberData $data): FamilyInvite
    {
        return DB::transaction(function () use ($family, $actor, $data): FamilyInvite {
            $lockedFamily = Family::query()->lockForUpdate()->findOrFail($family->getKey());
            $this->ensureCanManage($lockedFamily, $actor);

            if (! $lockedFamily->isActive()) {
                throw ValidationException::withMessages([
                    'family' => 'This family cannot send invites.',
                ]);
            }

            $role = in_array($data->role, FamilyMember::assignableRoles(), true)
                ? $data->role
                : FamilyMember::ROLE_MEMBER;

            $invitedUser = null;
            if ($data->userId !== null) {
                $invitedUser = User::query()->lockForUpdate()->find($data->userId);
                if ($invitedUser === null) {
                    throw ValidationException::withMessages([
                        'user_id' => 'The selected user is invalid.',
                    ]);
                }

                if ($this->findApprovedMembershipForUser($invitedUser) !== null) {
                    throw ValidationException::withMessages([
                        'user_id' => 'This user already belongs to a family.',
                    ]);
                }

                $existing = FamilyMember::query()
                    ->where('family_id', $lockedFamily->getKey())
                    ->where('user_id', $invitedUser->getKey())
                    ->lockForUpdate()
                    ->first();

                $now = now();
                if ($existing === null) {
                    FamilyMember::query()->create([
                        'family_id' => $lockedFamily->getKey(),
                        'user_id' => $invitedUser->getKey(),
                        'role' => $role,
                        'status' => FamilyMember::STATUS_INVITED,
                        'invited_at' => $now,
                        'invited_by' => $actor->getKey(),
                        'contribution_xp' => 0,
                        'contribution_gifts' => 0,
                    ]);
                } else {
                    if ($existing->isApproved()) {
                        throw ValidationException::withMessages([
                            'user_id' => 'This user is already a member.',
                        ]);
                    }
                    $existing->forceFill([
                        'role' => $role,
                        'status' => FamilyMember::STATUS_INVITED,
                        'invited_at' => $now,
                        'invited_by' => $actor->getKey(),
                        'removed_at' => null,
                        'reviewed_at' => null,
                        'reviewed_by' => null,
                    ])->save();
                }
            }

            $code = strtoupper(Str::random(8));
            while (FamilyInvite::query()->where('code', $code)->exists()) {
                $code = strtoupper(Str::random(8));
            }

            $hours = max(1, min(720, $data->expiresInHours ?? 72));

            return FamilyInvite::query()->create([
                'family_id' => $lockedFamily->getKey(),
                'invited_by' => $actor->getKey(),
                'invited_user_id' => $invitedUser?->getKey(),
                'code' => $code,
                'role' => $role,
                'status' => FamilyInvite::STATUS_PENDING,
                'expires_at' => now()->addHours($hours),
            ])->fresh(['inviter.profile', 'invitedUser.profile', 'family']);
        });
    }

    public function joinByCode(User $user, JoinFamilyData $data): FamilyMember
    {
        return DB::transaction(function () use ($user, $data): FamilyMember {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($this->findApprovedMembershipForUser($lockedUser) !== null) {
                throw ValidationException::withMessages([
                    'code' => 'You already belong to a family.',
                ]);
            }

            $invite = FamilyInvite::query()
                ->where('code', strtoupper(trim($data->code)))
                ->lockForUpdate()
                ->first();

            if ($invite === null || ! $invite->isPending()) {
                throw ValidationException::withMessages([
                    'code' => 'Invite code is invalid.',
                ]);
            }

            if ($invite->isExpired()) {
                $invite->forceFill(['status' => FamilyInvite::STATUS_EXPIRED])->save();
                throw ValidationException::withMessages([
                    'code' => 'Invite code has expired.',
                ]);
            }

            if ($invite->invited_user_id !== null && $invite->invited_user_id !== $lockedUser->getKey()) {
                throw ValidationException::withMessages([
                    'code' => 'This invite is assigned to another user.',
                ]);
            }

            $family = Family::query()->lockForUpdate()->findOrFail($invite->family_id);
            if (! $family->isActive()) {
                throw ValidationException::withMessages([
                    'code' => 'This family is not accepting members.',
                ]);
            }

            if ($family->members_count >= $family->max_members) {
                throw ValidationException::withMessages([
                    'code' => 'This family is full.',
                ]);
            }

            $now = now();
            $member = FamilyMember::query()
                ->where('family_id', $family->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            $payload = [
                'role' => in_array($invite->role, FamilyMember::assignableRoles(), true)
                    ? $invite->role
                    : FamilyMember::ROLE_MEMBER,
                'status' => FamilyMember::STATUS_APPROVED,
                'joined_at' => $now,
                'reviewed_at' => $now,
                'reviewed_by' => $invite->invited_by,
                'invited_at' => $member?->invited_at ?? $now,
                'invited_by' => $invite->invited_by,
                'removed_at' => null,
            ];

            if ($member === null) {
                $member = FamilyMember::query()->create(array_merge($payload, [
                    'family_id' => $family->getKey(),
                    'user_id' => $lockedUser->getKey(),
                    'contribution_xp' => 0,
                    'contribution_gifts' => 0,
                ]));
            } else {
                if ($member->isApproved()) {
                    throw ValidationException::withMessages([
                        'code' => 'You are already a member of this family.',
                    ]);
                }
                $member->forceFill($payload)->save();
            }

            $invite->forceFill([
                'status' => FamilyInvite::STATUS_ACCEPTED,
                'accepted_at' => $now,
                'invited_user_id' => $lockedUser->getKey(),
            ])->save();

            $family->increment('members_count');
            $this->bumpMission($family, 'weekly_members', 1);
            $this->awardXp($family, $member, 15);

            return $member->fresh(['user.profile', 'user.socialStatus', 'family']);
        });
    }

    public function approve(Family $family, User $applicant, User $reviewer): FamilyMember
    {
        return DB::transaction(function () use ($family, $applicant, $reviewer): FamilyMember {
            $lockedFamily = Family::query()->lockForUpdate()->findOrFail($family->getKey());
            $this->ensureCanManage($lockedFamily, $reviewer);
            $member = $this->lockPendingOrInvited($lockedFamily, $applicant);

            if ($lockedFamily->members_count >= $lockedFamily->max_members) {
                throw ValidationException::withMessages([
                    'user_id' => 'This family is full.',
                ]);
            }

            $otherApproved = FamilyMember::query()
                ->where('user_id', $applicant->getKey())
                ->where('status', FamilyMember::STATUS_APPROVED)
                ->whereKeyNot($member->getKey())
                ->lockForUpdate()
                ->exists();

            if ($otherApproved) {
                throw ValidationException::withMessages([
                    'user_id' => 'This user already belongs to another family.',
                ]);
            }

            $now = now();
            $member->forceFill([
                'status' => FamilyMember::STATUS_APPROVED,
                'role' => in_array($member->role, FamilyMember::assignableRoles(), true)
                    ? $member->role
                    : FamilyMember::ROLE_MEMBER,
                'joined_at' => $now,
                'reviewed_at' => $now,
                'reviewed_by' => $reviewer->getKey(),
                'removed_at' => null,
            ])->save();

            $lockedFamily->increment('members_count');
            $this->bumpMission($lockedFamily, 'weekly_members', 1);
            $this->awardXp($lockedFamily, $member, 15);

            return $member->fresh(['user.profile', 'user.socialStatus', 'family', 'reviewer']);
        });
    }

    public function reject(Family $family, User $applicant, User $reviewer): FamilyMember
    {
        return DB::transaction(function () use ($family, $applicant, $reviewer): FamilyMember {
            $lockedFamily = Family::query()->lockForUpdate()->findOrFail($family->getKey());
            $this->ensureCanManage($lockedFamily, $reviewer);
            $member = $this->lockPendingOrInvited($lockedFamily, $applicant);

            $member->forceFill([
                'status' => FamilyMember::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->getKey(),
            ])->save();

            return $member->fresh(['user.profile', 'user.socialStatus', 'family', 'reviewer']);
        });
    }

    public function removeMember(Family $family, User $memberUser, User $actor): FamilyMember
    {
        return DB::transaction(function () use ($family, $memberUser, $actor): FamilyMember {
            $lockedFamily = Family::query()->lockForUpdate()->findOrFail($family->getKey());
            $this->ensureCanManage($lockedFamily, $actor);

            $member = FamilyMember::query()
                ->where('family_id', $lockedFamily->getKey())
                ->where('user_id', $memberUser->getKey())
                ->lockForUpdate()
                ->first();

            if ($member === null || ! $member->isApproved()) {
                throw ValidationException::withMessages([
                    'user' => 'Approved family member not found.',
                ]);
            }

            if ($member->isOwnerRole() || $lockedFamily->isOwnedBy($memberUser)) {
                throw ValidationException::withMessages([
                    'user' => 'Family owners cannot be removed.',
                ]);
            }

            $actorMembership = $this->approvedMembership($lockedFamily, $actor);
            if ($member->role === FamilyMember::ROLE_ADMIN && ! $actorMembership->isOwnerRole()) {
                throw ValidationException::withMessages([
                    'user' => 'Only the family owner can remove admins.',
                ]);
            }

            $member->forceFill([
                'status' => FamilyMember::STATUS_REMOVED,
                'removed_at' => now(),
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            $lockedFamily->decrement('members_count');

            return $member->fresh(['user.profile', 'user.socialStatus', 'family']);
        });
    }

    public function updateRole(Family $family, User $actor, UpdateMemberRoleData $data): FamilyMember
    {
        return DB::transaction(function () use ($family, $actor, $data): FamilyMember {
            $lockedFamily = Family::query()->lockForUpdate()->findOrFail($family->getKey());

            if (! $lockedFamily->isOwnedBy($actor)) {
                throw ValidationException::withMessages([
                    'role' => 'Only the family owner can change roles.',
                ]);
            }

            if (! in_array($data->role, FamilyMember::assignableRoles(), true)) {
                throw ValidationException::withMessages([
                    'role' => 'Role must be admin or member.',
                ]);
            }

            $member = FamilyMember::query()
                ->where('family_id', $lockedFamily->getKey())
                ->where('user_id', $data->userId)
                ->where('status', FamilyMember::STATUS_APPROVED)
                ->lockForUpdate()
                ->first();

            if ($member === null) {
                throw ValidationException::withMessages([
                    'user_id' => 'Approved family member not found.',
                ]);
            }

            if ($member->isOwnerRole()) {
                throw ValidationException::withMessages([
                    'user_id' => 'Owner role cannot be changed.',
                ]);
            }

            $member->forceFill(['role' => $data->role])->save();

            return $member->fresh(['user.profile', 'user.socialStatus', 'family']);
        });
    }

    public function listMembers(Family $family): Collection
    {
        return FamilyMember::query()
            ->where('family_id', $family->getKey())
            ->whereIn('status', [
                FamilyMember::STATUS_APPROVED,
                FamilyMember::STATUS_PENDING,
                FamilyMember::STATUS_INVITED,
            ])
            ->with(['user.profile', 'user.socialStatus'])
            ->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")
            ->orderByDesc('joined_at')
            ->get();
    }

    public function rankings(int $perPage): LengthAwarePaginator
    {
        return Family::query()
            ->where('status', Family::STATUS_ACTIVE)
            ->with(['owner.profile', 'owner.socialStatus'])
            ->orderByDesc('ranking_score')
            ->orderByDesc('level')
            ->orderByDesc('xp')
            ->paginate($perPage);
    }

    public function sendMessage(Family $family, User $user, SendFamilyMessageData $data): FamilyMessage
    {
        return DB::transaction(function () use ($family, $user, $data): FamilyMessage {
            $lockedFamily = Family::query()->lockForUpdate()->findOrFail($family->getKey());
            $member = $this->approvedMembership($lockedFamily, $user);

            $message = FamilyMessage::query()->create([
                'family_id' => $lockedFamily->getKey(),
                'user_id' => $user->getKey(),
                'body' => $data->body,
            ]);

            $this->bumpMission($lockedFamily, 'daily_chat', 1);
            $this->awardXp($lockedFamily, $member, 2);

            return $message->fresh(['user.profile', 'user.socialStatus']);
        });
    }

    public function listMessages(Family $family, int $perPage): LengthAwarePaginator
    {
        return FamilyMessage::query()
            ->where('family_id', $family->getKey())
            ->with(['user.profile', 'user.socialStatus'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function sendGift(Family $family, User $sender, SendFamilyGiftData $data): FamilyGift
    {
        return DB::transaction(function () use ($family, $sender, $data): FamilyGift {
            $lockedFamily = Family::query()->lockForUpdate()->findOrFail($family->getKey());
            $member = $this->approvedMembership($lockedFamily, $sender);
            $lockedSender = User::query()->lockForUpdate()->findOrFail($sender->getKey());

            $gift = Gift::query()->lockForUpdate()->find($data->giftId);
            if ($gift === null || $gift->status !== Gift::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'gift_id' => 'The selected gift is invalid.',
                ]);
            }

            $quantity = max(1, min(99, $data->quantity));
            $coins = (int) $gift->coin_price * $quantity;
            if ($coins < 1) {
                throw ValidationException::withMessages([
                    'gift_id' => 'Gift price is invalid.',
                ]);
            }

            $wallet = $this->wallets->lockForUser($lockedSender);
            if ($wallet->coins_balance < $coins) {
                throw ValidationException::withMessages([
                    'gift_id' => 'Insufficient coins.',
                ]);
            }

            $now = now();
            $xpAwarded = max(5, (int) floor($coins / 10));

            $wallet->decrement('coins_balance', $coins);
            WalletTransaction::query()->create([
                'user_id' => $lockedSender->getKey(),
                'type' => WalletTransaction::TYPE_FAMILY_GIFT,
                'amount' => -$coins,
                'currency' => WalletTransaction::CURRENCY_COINS,
                'reference_type' => Family::class,
                'reference_id' => $lockedFamily->getKey(),
                'metadata' => [
                    'family_id' => $lockedFamily->getKey(),
                    'gift_id' => $gift->getKey(),
                    'quantity' => $quantity,
                ],
                'created_at' => $now,
            ]);

            $familyGift = FamilyGift::query()->create([
                'family_id' => $lockedFamily->getKey(),
                'sender_id' => $lockedSender->getKey(),
                'gift_id' => $gift->getKey(),
                'quantity' => $quantity,
                'coins' => $coins,
                'xp_awarded' => $xpAwarded,
                'metadata' => [
                    'gift_name' => $gift->name,
                    'gift_icon' => $gift->icon,
                ],
                'created_at' => $now,
            ]);

            $lockedFamily->increment('wallet_coins', $coins);
            $lockedFamily->increment('total_gifts_received', $coins);
            $lockedFamily->increment('total_gifts_sent', $coins);
            $lockedFamily->increment('ranking_score', $coins);

            FamilyWalletTransaction::query()->create([
                'family_id' => $lockedFamily->getKey(),
                'user_id' => $lockedSender->getKey(),
                'type' => FamilyWalletTransaction::TYPE_GIFT_RECEIVED,
                'amount' => $coins,
                'currency' => FamilyWalletTransaction::CURRENCY_COINS,
                'note' => 'Family gift',
                'metadata' => [
                    'family_gift_id' => $familyGift->getKey(),
                    'gift_id' => $gift->getKey(),
                    'quantity' => $quantity,
                ],
                'created_at' => $now,
            ]);

            $member->increment('contribution_gifts', $coins);
            $this->bumpMission($lockedFamily, 'daily_gift', 1);
            $this->awardXp($lockedFamily, $member, $xpAwarded);

            return $familyGift->fresh(['sender.profile', 'sender.socialStatus', 'gift', 'family']);
        });
    }

    public function listGifts(Family $family, int $perPage): LengthAwarePaginator
    {
        return FamilyGift::query()
            ->where('family_id', $family->getKey())
            ->with(['sender.profile', 'sender.socialStatus', 'gift'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function contribute(Family $family, User $user, ContributeWalletData $data): FamilyWalletTransaction
    {
        return DB::transaction(function () use ($family, $user, $data): FamilyWalletTransaction {
            $lockedFamily = Family::query()->lockForUpdate()->findOrFail($family->getKey());
            $member = $this->approvedMembership($lockedFamily, $user);
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $amount = max(1, $data->amount);
            $currency = $data->currency === FamilyWalletTransaction::CURRENCY_DIAMONDS
                ? FamilyWalletTransaction::CURRENCY_DIAMONDS
                : FamilyWalletTransaction::CURRENCY_COINS;

            $wallet = $this->wallets->lockForUser($lockedUser);
            if ($currency === FamilyWalletTransaction::CURRENCY_DIAMONDS) {
                if ($wallet->diamonds_balance < $amount) {
                    throw ValidationException::withMessages([
                        'amount' => 'Insufficient diamonds.',
                    ]);
                }
                $wallet->decrement('diamonds_balance', $amount);
                $lockedFamily->increment('wallet_diamonds', $amount);
                $walletCurrency = WalletTransaction::CURRENCY_DIAMONDS;
            } else {
                if ($wallet->coins_balance < $amount) {
                    throw ValidationException::withMessages([
                        'amount' => 'Insufficient coins.',
                    ]);
                }
                $wallet->decrement('coins_balance', $amount);
                $lockedFamily->increment('wallet_coins', $amount);
                $walletCurrency = WalletTransaction::CURRENCY_COINS;
            }

            $now = now();
            WalletTransaction::query()->create([
                'user_id' => $lockedUser->getKey(),
                'type' => WalletTransaction::TYPE_FAMILY_CONTRIBUTE,
                'amount' => -$amount,
                'currency' => $walletCurrency,
                'reference_type' => Family::class,
                'reference_id' => $lockedFamily->getKey(),
                'metadata' => [
                    'family_id' => $lockedFamily->getKey(),
                    'note' => $data->note,
                ],
                'created_at' => $now,
            ]);

            $tx = FamilyWalletTransaction::query()->create([
                'family_id' => $lockedFamily->getKey(),
                'user_id' => $lockedUser->getKey(),
                'type' => FamilyWalletTransaction::TYPE_CONTRIBUTE,
                'amount' => $amount,
                'currency' => $currency,
                'note' => $data->note,
                'metadata' => null,
                'created_at' => $now,
            ]);

            $lockedFamily->increment('ranking_score', $amount);
            $this->bumpMission($lockedFamily, 'daily_contribute', 1);
            $this->awardXp($lockedFamily, $member, max(5, (int) floor($amount / 20)));

            return $tx->fresh(['user.profile', 'user.socialStatus', 'family']);
        });
    }

    public function listWalletTransactions(Family $family, int $perPage): LengthAwarePaginator
    {
        return FamilyWalletTransaction::query()
            ->where('family_id', $family->getKey())
            ->with(['user.profile', 'user.socialStatus'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function listMissions(Family $family): Collection
    {
        $missions = FamilyMission::query()
            ->where('is_active', true)
            ->orderBy('period')
            ->orderBy('title')
            ->get();

        return $missions->map(function (FamilyMission $mission) use ($family): array {
            $periodKey = $this->periodKey($mission->period);
            $progress = FamilyMissionProgress::query()
                ->where('family_id', $family->getKey())
                ->where('mission_id', $mission->getKey())
                ->where('period_key', $periodKey)
                ->first();

            return [
                'id' => $mission->id,
                'key' => $mission->key,
                'title' => $mission->title,
                'description' => $mission->description,
                'period' => $mission->period,
                'target_value' => $mission->target_value,
                'reward_xp' => $mission->reward_xp,
                'reward_coins' => $mission->reward_coins,
                'progress' => $progress?->progress ?? 0,
                'completed_at' => $progress?->completed_at?->toISOString(),
                'claimed_at' => $progress?->claimed_at?->toISOString(),
                'is_claimable' => $progress !== null
                    && $progress->isCompleted()
                    && ! $progress->isClaimed(),
            ];
        });
    }

    public function claimMission(Family $family, User $actor, string $missionId): FamilyMissionProgress
    {
        return DB::transaction(function () use ($family, $actor, $missionId): FamilyMissionProgress {
            $lockedFamily = Family::query()->lockForUpdate()->findOrFail($family->getKey());
            $member = $this->approvedMembership($lockedFamily, $actor);

            $mission = FamilyMission::query()->lockForUpdate()->find($missionId);
            if ($mission === null || ! $mission->is_active) {
                throw ValidationException::withMessages([
                    'mission' => 'Mission not found.',
                ]);
            }

            $periodKey = $this->periodKey($mission->period);
            $progress = FamilyMissionProgress::query()
                ->where('family_id', $lockedFamily->getKey())
                ->where('mission_id', $mission->getKey())
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if ($progress === null || ! $progress->isCompleted()) {
                throw ValidationException::withMessages([
                    'mission' => 'Mission is not completed yet.',
                ]);
            }

            if ($progress->isClaimed()) {
                throw ValidationException::withMessages([
                    'mission' => 'Mission reward already claimed.',
                ]);
            }

            $now = now();
            $progress->forceFill(['claimed_at' => $now])->save();

            if ($mission->reward_coins > 0) {
                $lockedFamily->increment('wallet_coins', $mission->reward_coins);
                FamilyWalletTransaction::query()->create([
                    'family_id' => $lockedFamily->getKey(),
                    'user_id' => $actor->getKey(),
                    'type' => FamilyWalletTransaction::TYPE_MISSION_REWARD,
                    'amount' => $mission->reward_coins,
                    'currency' => FamilyWalletTransaction::CURRENCY_COINS,
                    'note' => $mission->title,
                    'metadata' => ['mission_id' => $mission->getKey()],
                    'created_at' => $now,
                ]);
            }

            $this->awardXp($lockedFamily, $member, $mission->reward_xp);

            return $progress->fresh('mission');
        });
    }

    private function ensureCanManage(Family $family, User $actor): void
    {
        $membership = $this->approvedMembership($family, $actor);
        if (! $membership->isAdminRole()) {
            throw ValidationException::withMessages([
                'family' => 'Only family owners and admins can manage members.',
            ]);
        }
    }

    private function approvedMembership(Family $family, User $user): FamilyMember
    {
        $member = FamilyMember::query()
            ->where('family_id', $family->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', FamilyMember::STATUS_APPROVED)
            ->lockForUpdate()
            ->first();

        if ($member === null) {
            throw ValidationException::withMessages([
                'family' => 'You must be an approved family member.',
            ]);
        }

        return $member;
    }

    private function lockPendingOrInvited(Family $family, User $applicant): FamilyMember
    {
        $member = FamilyMember::query()
            ->where('family_id', $family->getKey())
            ->where('user_id', $applicant->getKey())
            ->lockForUpdate()
            ->first();

        if ($member === null || (! $member->isPending() && ! $member->isInvited())) {
            throw ValidationException::withMessages([
                'user_id' => 'Pending invite or application not found.',
            ]);
        }

        return $member;
    }

    private function bumpMission(Family $family, string $key, int $amount): void
    {
        $mission = FamilyMission::query()->where('key', $key)->where('is_active', true)->first();
        if ($mission === null) {
            return;
        }

        $periodKey = $this->periodKey($mission->period);
        $progress = FamilyMissionProgress::query()->firstOrCreate(
            [
                'family_id' => $family->getKey(),
                'mission_id' => $mission->getKey(),
                'period_key' => $periodKey,
            ],
            [
                'progress' => 0,
            ],
        );

        if ($progress->isClaimed()) {
            return;
        }

        $progress->progress = min($mission->target_value, $progress->progress + $amount);
        if ($progress->progress >= $mission->target_value && $progress->completed_at === null) {
            $progress->completed_at = now();
        }
        $progress->save();
    }

    private function awardXp(Family $family, FamilyMember $member, int $xp): void
    {
        if ($xp < 1) {
            return;
        }

        $member->increment('contribution_xp', $xp);
        $family->refresh();

        $level = (int) $family->level;
        $currentXp = (int) $family->xp + $xp;
        $toNext = (int) $family->xp_to_next_level;

        while ($currentXp >= $toNext) {
            $currentXp -= $toNext;
            $level++;
            $toNext = Family::xpRequiredForLevel($level);
        }

        $family->forceFill([
            'xp' => $currentXp,
            'level' => $level,
            'xp_to_next_level' => $toNext,
            'badge_key' => Family::badgeKeyForLevel($level),
            'ranking_score' => (int) $family->ranking_score + $xp,
        ])->save();
    }

    private function periodKey(string $period): string
    {
        return $period === FamilyMission::PERIOD_WEEKLY
            ? now()->format('o-\WW')
            : now()->toDateString();
    }
}
