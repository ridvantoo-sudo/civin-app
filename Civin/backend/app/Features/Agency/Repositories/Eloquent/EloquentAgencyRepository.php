<?php

namespace App\Features\Agency\Repositories\Eloquent;

use App\Features\Agency\DTOs\ApplyAgencyData;
use App\Features\Agency\DTOs\CreateAgencyData;
use App\Features\Agency\Models\Agency;
use App\Features\Agency\Models\AgencyCommission;
use App\Features\Agency\Models\AgencyMember;
use App\Features\Agency\Repositories\Contracts\AgencyRepository;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentAgencyRepository implements AgencyRepository
{
    public function __construct(private readonly WalletRepository $wallets) {}

    public function find(string $agencyId): ?Agency
    {
        return Agency::query()
            ->with(['owner.profile', 'owner.socialStatus'])
            ->find($agencyId);
    }

    public function findOwnedBy(User $user): ?Agency
    {
        return Agency::query()
            ->where('owner_id', $user->getKey())
            ->first();
    }

    public function findApprovedMembershipForUser(User|string $user): ?AgencyMember
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return AgencyMember::query()
            ->where('user_id', $userId)
            ->where('status', AgencyMember::STATUS_APPROVED)
            ->with('agency')
            ->first();
    }

    public function create(User $owner, CreateAgencyData $data, string $slug): Agency
    {
        return DB::transaction(function () use ($owner, $data, $slug): Agency {
            $lockedOwner = User::query()->lockForUpdate()->findOrFail($owner->getKey());

            if ($this->findOwnedBy($lockedOwner) !== null) {
                throw ValidationException::withMessages([
                    'name' => 'You already own an agency.',
                ]);
            }

            if ($this->findApprovedMembershipForUser($lockedOwner) !== null) {
                throw ValidationException::withMessages([
                    'name' => 'You already belong to an agency.',
                ]);
            }

            $now = now();

            $agency = Agency::query()->create([
                'owner_id' => $lockedOwner->getKey(),
                'name' => $data->name,
                'slug' => $slug,
                'description' => $data->description,
                'logo' => $data->logo,
                'commission_rate' => $data->commissionRate,
                'status' => Agency::STATUS_ACTIVE,
                'members_count' => 1,
                'hosts_count' => 0,
                'total_gross_earnings' => 0,
                'total_commission' => 0,
            ]);

            AgencyMember::query()->create([
                'agency_id' => $agency->getKey(),
                'user_id' => $lockedOwner->getKey(),
                'role' => AgencyMember::ROLE_OWNER,
                'status' => AgencyMember::STATUS_APPROVED,
                'message' => null,
                'applied_at' => $now,
                'reviewed_at' => $now,
                'reviewed_by' => $lockedOwner->getKey(),
                'removed_at' => null,
                'gross_earnings' => 0,
                'commission_paid' => 0,
            ]);

            return $agency->fresh(['owner.profile', 'owner.socialStatus']);
        });
    }

    public function apply(Agency $agency, User $user, ApplyAgencyData $data): AgencyMember
    {
        return DB::transaction(function () use ($agency, $user, $data): AgencyMember {
            $lockedAgency = Agency::query()->lockForUpdate()->findOrFail($agency->getKey());
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $lockedAgency->isActive()) {
                throw ValidationException::withMessages([
                    'agency' => 'This agency is not accepting applications.',
                ]);
            }

            if ($lockedAgency->isOwnedBy($lockedUser)) {
                throw ValidationException::withMessages([
                    'agency' => 'Agency owners cannot apply as hosts.',
                ]);
            }

            $existingApproved = AgencyMember::query()
                ->where('user_id', $lockedUser->getKey())
                ->where('status', AgencyMember::STATUS_APPROVED)
                ->lockForUpdate()
                ->first();

            if ($existingApproved !== null) {
                throw ValidationException::withMessages([
                    'agency' => 'You already belong to an agency.',
                ]);
            }

            $existing = AgencyMember::query()
                ->where('agency_id', $lockedAgency->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->isPending()) {
                throw ValidationException::withMessages([
                    'agency' => 'You already have a pending application for this agency.',
                ]);
            }

            if ($existing !== null && $existing->isApproved()) {
                throw ValidationException::withMessages([
                    'agency' => 'You are already a member of this agency.',
                ]);
            }

            $now = now();
            $payload = [
                'role' => AgencyMember::ROLE_HOST,
                'status' => AgencyMember::STATUS_PENDING,
                'message' => $data->message,
                'applied_at' => $now,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'removed_at' => null,
            ];

            if ($existing === null) {
                $member = AgencyMember::query()->create(array_merge($payload, [
                    'agency_id' => $lockedAgency->getKey(),
                    'user_id' => $lockedUser->getKey(),
                    'gross_earnings' => 0,
                    'commission_paid' => 0,
                ]));
            } else {
                $existing->forceFill($payload)->save();
                $member = $existing;
            }

            return $member->fresh(['user.profile', 'user.socialStatus', 'agency']);
        });
    }

    public function approve(Agency $agency, User $applicant, User $reviewer): AgencyMember
    {
        return DB::transaction(function () use ($agency, $applicant, $reviewer): AgencyMember {
            $lockedAgency = Agency::query()->lockForUpdate()->findOrFail($agency->getKey());
            $member = $this->lockPendingApplication($lockedAgency, $applicant);

            $otherApproved = AgencyMember::query()
                ->where('user_id', $applicant->getKey())
                ->where('status', AgencyMember::STATUS_APPROVED)
                ->whereKeyNot($member->getKey())
                ->lockForUpdate()
                ->exists();

            if ($otherApproved) {
                throw ValidationException::withMessages([
                    'user_id' => 'This user already belongs to another agency.',
                ]);
            }

            $now = now();
            $member->forceFill([
                'status' => AgencyMember::STATUS_APPROVED,
                'role' => AgencyMember::ROLE_HOST,
                'reviewed_at' => $now,
                'reviewed_by' => $reviewer->getKey(),
                'removed_at' => null,
            ])->save();

            $lockedAgency->increment('members_count');
            $lockedAgency->increment('hosts_count');

            return $member->fresh(['user.profile', 'user.socialStatus', 'agency', 'reviewer']);
        });
    }

    public function reject(Agency $agency, User $applicant, User $reviewer): AgencyMember
    {
        return DB::transaction(function () use ($agency, $applicant, $reviewer): AgencyMember {
            $lockedAgency = Agency::query()->lockForUpdate()->findOrFail($agency->getKey());
            $member = $this->lockPendingApplication($lockedAgency, $applicant);

            $member->forceFill([
                'status' => AgencyMember::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->getKey(),
            ])->save();

            return $member->fresh(['user.profile', 'user.socialStatus', 'agency', 'reviewer']);
        });
    }

    public function removeMember(Agency $agency, User $memberUser, User $actor): AgencyMember
    {
        return DB::transaction(function () use ($agency, $memberUser, $actor): AgencyMember {
            $lockedAgency = Agency::query()->lockForUpdate()->findOrFail($agency->getKey());

            $member = AgencyMember::query()
                ->where('agency_id', $lockedAgency->getKey())
                ->where('user_id', $memberUser->getKey())
                ->lockForUpdate()
                ->first();

            if ($member === null || ! $member->isApproved()) {
                throw ValidationException::withMessages([
                    'user' => 'Approved agency member not found.',
                ]);
            }

            if ($member->isOwnerRole() || $lockedAgency->isOwnedBy($memberUser)) {
                throw ValidationException::withMessages([
                    'user' => 'Agency owners cannot be removed.',
                ]);
            }

            $member->forceFill([
                'status' => AgencyMember::STATUS_REMOVED,
                'removed_at' => now(),
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            $lockedAgency->decrement('members_count');
            if ($member->isHost()) {
                $lockedAgency->decrement('hosts_count');
            }

            return $member->fresh(['user.profile', 'user.socialStatus', 'agency']);
        });
    }

    public function approvedHosts(Agency $agency): Collection
    {
        return AgencyMember::query()
            ->where('agency_id', $agency->getKey())
            ->where('role', AgencyMember::ROLE_HOST)
            ->where('status', AgencyMember::STATUS_APPROVED)
            ->with(['user.profile', 'user.socialStatus'])
            ->orderByDesc('reviewed_at')
            ->orderBy('created_at')
            ->get();
    }

    public function earnings(Agency $agency, int $perPage): LengthAwarePaginator
    {
        return AgencyCommission::query()
            ->where('agency_id', $agency->getKey())
            ->with(['host.profile', 'host.socialStatus', 'member'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function createCommissionFromGift(GiftTransaction $transaction): ?AgencyCommission
    {
        return DB::transaction(function () use ($transaction): ?AgencyCommission {
            $existing = AgencyCommission::query()
                ->where('source_type', $transaction->getMorphClass())
                ->where('source_id', $transaction->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $member = AgencyMember::query()
                ->where('user_id', $transaction->receiver_id)
                ->where('status', AgencyMember::STATUS_APPROVED)
                ->where('role', AgencyMember::ROLE_HOST)
                ->lockForUpdate()
                ->first();

            if ($member === null) {
                return null;
            }

            $agency = Agency::query()->lockForUpdate()->find($member->agency_id);
            if ($agency === null || ! $agency->isActive()) {
                return null;
            }

            $gross = (int) $transaction->coins;
            if ($gross < 1) {
                return null;
            }

            $rate = (float) $agency->commission_rate;
            $commissionAmount = (int) floor($gross * ($rate / 100));
            if ($commissionAmount < 1) {
                return null;
            }

            $host = User::query()->lockForUpdate()->findOrFail($transaction->receiver_id);
            $owner = User::query()->lockForUpdate()->findOrFail($agency->owner_id);

            $hostWallet = $this->wallets->lockForUser($host);
            if ($hostWallet->diamonds_balance < $commissionAmount) {
                return null;
            }

            $ownerWallet = $this->wallets->lockForUser($owner);
            $now = now();
            $hostNet = $gross - $commissionAmount;

            $commission = AgencyCommission::query()->create([
                'agency_id' => $agency->getKey(),
                'host_id' => $host->getKey(),
                'agency_member_id' => $member->getKey(),
                'source_type' => $transaction->getMorphClass(),
                'source_id' => $transaction->getKey(),
                'gross_amount' => $gross,
                'commission_rate' => $rate,
                'commission_amount' => $commissionAmount,
                'host_net_amount' => $hostNet,
                'currency' => AgencyCommission::CURRENCY_DIAMONDS,
                'metadata' => [
                    'gift_id' => $transaction->gift_id,
                    'room_id' => $transaction->room_id,
                    'sender_id' => $transaction->sender_id,
                    'quantity' => $transaction->quantity,
                ],
                'created_at' => $now,
            ]);

            $hostWallet->decrement('diamonds_balance', $commissionAmount);
            $ownerWallet->increment('diamonds_balance', $commissionAmount);

            WalletTransaction::query()->create([
                'user_id' => $host->getKey(),
                'type' => AgencyCommission::WALLET_TYPE_DEBIT,
                'amount' => -$commissionAmount,
                'currency' => WalletTransaction::CURRENCY_DIAMONDS,
                'reference_type' => $commission->getMorphClass(),
                'reference_id' => $commission->getKey(),
                'metadata' => [
                    'agency_id' => $agency->getKey(),
                    'gift_transaction_id' => $transaction->getKey(),
                    'gross_amount' => $gross,
                    'commission_rate' => $rate,
                ],
                'created_at' => $now,
            ]);

            WalletTransaction::query()->create([
                'user_id' => $owner->getKey(),
                'type' => AgencyCommission::WALLET_TYPE_CREDIT,
                'amount' => $commissionAmount,
                'currency' => WalletTransaction::CURRENCY_DIAMONDS,
                'reference_type' => $commission->getMorphClass(),
                'reference_id' => $commission->getKey(),
                'metadata' => [
                    'agency_id' => $agency->getKey(),
                    'host_id' => $host->getKey(),
                    'gift_transaction_id' => $transaction->getKey(),
                    'gross_amount' => $gross,
                    'commission_rate' => $rate,
                ],
                'created_at' => $now,
            ]);

            $member->increment('gross_earnings', $gross);
            $member->increment('commission_paid', $commissionAmount);
            $agency->increment('total_gross_earnings', $gross);
            $agency->increment('total_commission', $commissionAmount);

            $commission->load(['agency', 'host', 'member']);

            return $commission;
        });
    }

    private function lockPendingApplication(Agency $agency, User $applicant): AgencyMember
    {
        $member = AgencyMember::query()
            ->where('agency_id', $agency->getKey())
            ->where('user_id', $applicant->getKey())
            ->lockForUpdate()
            ->first();

        if ($member === null || ! $member->isPending()) {
            throw ValidationException::withMessages([
                'user_id' => 'Pending host application not found.',
            ]);
        }

        return $member;
    }
}
