<?php

namespace App\Features\Svip\Services;

use App\Features\Svip\DTOs\PurchaseSvipData;
use App\Features\Svip\DTOs\SvipPrivilegesData;
use App\Features\Svip\DTOs\UpgradeSvipData;
use App\Features\Svip\Events\SvipActivated;
use App\Features\Svip\Events\SvipExpired;
use App\Features\Svip\Models\UserSvip;
use App\Features\Svip\Repositories\Contracts\SvipRepository;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class SvipService
{
    private const PURCHASE_RATE_KEY = 'svip-purchase:%s';

    private const UPGRADE_RATE_KEY = 'svip-upgrade:%s';

    private const RATE_MAX = 10;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private SvipRepository $svips,
        private WalletRepository $wallets,
    ) {}

    public function levels(): Collection
    {
        return $this->svips->activeLevels();
    }

    public function me(User $user): ?UserSvip
    {
        $subscription = $this->svips->findForUser($user);

        if ($subscription === null) {
            return null;
        }

        if ($subscription->isExpired()) {
            $this->expireSubscription($subscription);

            return null;
        }

        return $subscription->loadMissing('level');
    }

    public function privileges(User $user): ?SvipPrivilegesData
    {
        $subscription = $this->me($user);

        if ($subscription === null || $subscription->level === null) {
            return null;
        }

        return SvipPrivilegesData::fromLevelPrivileges($subscription->level->privileges());
    }

    public function hasPrivilege(User $user, string $privilege): bool
    {
        $privileges = $this->privileges($user);

        if ($privileges === null) {
            return false;
        }

        return match ($privilege) {
            'badge' => filled($privileges->badge),
            'animated_profile' => filled($privileges->animatedProfile),
            'profile_frame' => filled($privileges->profileFrame),
            'chat_effect' => filled($privileges->chatEffect),
            'entrance_animation' => filled($privileges->entranceAnimation),
            'exclusive_gifts' => $privileges->exclusiveGifts,
            'exclusive_frames' => $privileges->exclusiveFrames,
            'special_privileges' => $privileges->specialPrivileges,
            default => false,
        };
    }

    public function purchase(User $user, PurchaseSvipData $data): UserSvip
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::PURCHASE_RATE_KEY, $user->getKey()), 'purchase');

        $level = $this->svips->findActiveLevel($data->svipLevelId);
        if ($level === null) {
            throw ValidationException::withMessages(['svip_level_id' => 'The selected SVIP level is unavailable.']);
        }

        $subscription = $this->svips->purchase($user, $level, $data);

        SvipActivated::dispatch($subscription);
        WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());

        return $subscription;
    }

    public function upgrade(User $user, UpgradeSvipData $data): UserSvip
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::UPGRADE_RATE_KEY, $user->getKey()), 'upgrade');

        $subscription = $this->svips->findForUser($user);
        if ($subscription === null || $subscription->isExpired()) {
            if ($subscription !== null && $subscription->isExpired()) {
                $this->expireSubscription($subscription);
            }

            throw ValidationException::withMessages([
                'svip' => 'You do not have an active SVIP subscription to upgrade.',
            ]);
        }

        $level = $this->svips->findActiveLevel($data->svipLevelId);
        if ($level === null) {
            throw ValidationException::withMessages(['svip_level_id' => 'The selected SVIP level is unavailable.']);
        }

        $upgraded = $this->svips->upgrade($user, $subscription, $level, $data);

        SvipActivated::dispatch($upgraded);
        WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());

        return $upgraded;
    }

    public function expireDueSubscriptions(): int
    {
        return (int) DB::transaction(function (): int {
            $expired = $this->svips->expiredActiveSubscriptions();
            $count = 0;

            foreach ($expired as $subscription) {
                $this->expireSubscription($subscription);
                $count++;
            }

            return $count;
        });
    }

    private function expireSubscription(UserSvip $subscription): void
    {
        $subscription->loadMissing(['level', 'user.profile']);
        $expired = $this->svips->expire($subscription);

        SvipExpired::dispatch($expired);
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can manage SVIP.');
        }
    }

    private function enforceRateLimit(string $key, string $action): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                $action => 'You are performing SVIP actions too quickly.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
