<?php

namespace App\Features\Agency\Services;

use App\Features\Agency\DTOs\ApplyAgencyData;
use App\Features\Agency\DTOs\CreateAgencyData;
use App\Features\Agency\DTOs\ReviewApplicationData;
use App\Features\Agency\Events\AgencyCommissionCreated;
use App\Features\Agency\Events\AgencyMemberJoined;
use App\Features\Agency\Models\Agency;
use App\Features\Agency\Models\AgencyCommission;
use App\Features\Agency\Models\AgencyMember;
use App\Features\Agency\Repositories\Contracts\AgencyRepository;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AgencyService
{
    private const CREATE_RATE_KEY = 'agency-create:%s';

    private const APPLY_RATE_KEY = 'agency-apply:%s:%s';

    private const REVIEW_RATE_KEY = 'agency-review:%s';

    private const RATE_MAX = 10;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private AgencyRepository $agencies,
        private WalletRepository $wallets,
    ) {}

    public function show(Agency $agency): Agency
    {
        return $this->agencies->find((string) $agency->getKey()) ?? $agency->loadMissing(['owner.profile', 'owner.socialStatus']);
    }

    public function create(User $owner, CreateAgencyData $data): Agency
    {
        $this->ensureEligible($owner);
        $this->enforceRateLimit(sprintf(self::CREATE_RATE_KEY, $owner->getKey()), 'agency');

        $slug = $this->uniqueSlug($data->name);

        return $this->agencies->create($owner, $data, $slug);
    }

    public function apply(Agency $agency, User $user, ApplyAgencyData $data): AgencyMember
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(
            sprintf(self::APPLY_RATE_KEY, $agency->getKey(), $user->getKey()),
            'agency',
        );

        return $this->agencies->apply($agency, $user, $data);
    }

    public function approve(Agency $agency, User $reviewer, ReviewApplicationData $data): AgencyMember
    {
        $this->ensureOwner($agency, $reviewer);
        $this->enforceRateLimit(sprintf(self::REVIEW_RATE_KEY, $agency->getKey()), 'agency');

        $applicant = User::query()->find($data->userId);
        if ($applicant === null) {
            throw ValidationException::withMessages(['user_id' => 'The selected user is invalid.']);
        }

        $member = $this->agencies->approve($agency, $applicant, $reviewer);
        AgencyMemberJoined::dispatch($member);

        return $member;
    }

    public function reject(Agency $agency, User $reviewer, ReviewApplicationData $data): AgencyMember
    {
        $this->ensureOwner($agency, $reviewer);
        $this->enforceRateLimit(sprintf(self::REVIEW_RATE_KEY, $agency->getKey()), 'agency');

        $applicant = User::query()->find($data->userId);
        if ($applicant === null) {
            throw ValidationException::withMessages(['user_id' => 'The selected user is invalid.']);
        }

        return $this->agencies->reject($agency, $applicant, $reviewer);
    }

    public function removeMember(Agency $agency, User $actor, User $memberUser): AgencyMember
    {
        $this->ensureOwner($agency, $actor);

        return $this->agencies->removeMember($agency, $memberUser, $actor);
    }

    public function hosts(Agency $agency, User $actor): Collection
    {
        $this->ensureOwner($agency, $actor);

        return $this->agencies->approvedHosts($agency);
    }

    public function earnings(Agency $agency, User $actor, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureOwner($agency, $actor);

        return $this->agencies->earnings($agency, max(1, min($perPage, 100)));
    }

    public function applyGiftCommission(GiftTransaction $transaction): ?AgencyCommission
    {
        $commission = $this->agencies->createCommissionFromGift($transaction);

        if ($commission === null) {
            return null;
        }

        if ($commission->wasRecentlyCreated) {
            AgencyCommissionCreated::dispatch($commission);

            $hostWallet = $this->wallets->findOrCreateForUser(
                User::query()->findOrFail($commission->host_id),
            );
            $ownerWallet = $this->wallets->findOrCreateForUser(
                User::query()->findOrFail($commission->agency->owner_id),
            );

            WalletUpdated::dispatch($hostWallet->fresh());
            WalletUpdated::dispatch($ownerWallet->fresh());
        }

        return $commission;
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can manage agency membership.');
        }
    }

    private function ensureOwner(Agency $agency, User $user): void
    {
        $this->ensureEligible($user);

        if (! $agency->isOwnedBy($user)) {
            throw new AuthorizationException('Only the agency owner can manage this agency.');
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'agency';
        }

        $slug = $base;
        $suffix = 0;

        while (Agency::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function enforceRateLimit(string $key, string $action): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                $action => 'You are performing agency actions too quickly.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
