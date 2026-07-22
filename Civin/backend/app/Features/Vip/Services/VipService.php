<?php

namespace App\Features\Vip\Services;

use App\Features\Users\Models\User;
use App\Features\Vip\DTOs\PurchaseVipData;
use App\Features\Vip\DTOs\UpgradeVipData;
use App\Features\Vip\DTOs\VipPrivilegesData;
use App\Features\Vip\Events\VipActivated;
use App\Features\Vip\Events\VipExpired;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Repositories\Contracts\VipRepository;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class VipService
{
    private const PURCHASE_RATE_KEY = 'vip-purchase:%s';

    private const UPGRADE_RATE_KEY = 'vip-upgrade:%s';

    private const RATE_MAX = 10;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private VipRepository $vips,
        private WalletRepository $wallets,
    ) {}

    public function levels(): Collection
    {
        return $this->vips->activeLevels();
    }

    public function me(User $user): ?UserVip
    {
        $subscription = $this->vips->findForUser($user);

        if ($subscription === null) {
            return null;
        }

        if ($subscription->isExpired()) {
            $this->expireSubscription($subscription);

            return null;
        }

        return $subscription->loadMissing('level');
    }

    public function privileges(User $user): ?VipPrivilegesData
    {
        $subscription = $this->me($user);

        if ($subscription === null || $subscription->level === null) {
            return null;
        }

        return VipPrivilegesData::fromLevelPrivileges($subscription->level->privileges());
    }

    public function hasPrivilege(User $user, string $privilege): bool
    {
        $privileges = $this->privileges($user);

        if ($privileges === null) {
            return false;
        }

        return match ($privilege) {
            'badge' => filled($privileges->badge),
            'profile_frame' => filled($privileges->profileFrame),
            'chat_effect' => filled($privileges->chatEffect),
            'entrance_animation' => filled($privileges->entranceAnimation),
            'exclusive_gifts' => $privileges->exclusiveGifts,
            default => false,
        };
    }

    public function purchase(User $user, PurchaseVipData $data): UserVip
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::PURCHASE_RATE_KEY, $user->getKey()), 'purchase');

        $level = $this->vips->findActiveLevel($data->vipLevelId);
        if ($level === null) {
            throw ValidationException::withMessages(['vip_level_id' => 'The selected VIP level is unavailable.']);
        }

        $subscription = $this->vips->purchase($user, $level, $data);

        VipActivated::dispatch($subscription);
        WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());

        return $subscription;
    }

    public function upgrade(User $user, UpgradeVipData $data): UserVip
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::UPGRADE_RATE_KEY, $user->getKey()), 'upgrade');

        $subscription = $this->vips->findForUser($user);
        if ($subscription === null || $subscription->isExpired()) {
            if ($subscription !== null && $subscription->isExpired()) {
                $this->expireSubscription($subscription);
            }

            throw ValidationException::withMessages([
                'vip' => 'You do not have an active VIP subscription to upgrade.',
            ]);
        }

        $level = $this->vips->findActiveLevel($data->vipLevelId);
        if ($level === null) {
            throw ValidationException::withMessages(['vip_level_id' => 'The selected VIP level is unavailable.']);
        }

        $upgraded = $this->vips->upgrade($user, $subscription, $level, $data);

        VipActivated::dispatch($upgraded);
        WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());

        return $upgraded;
    }

    public function expireDueSubscriptions(): int
    {
        return (int) DB::transaction(function (): int {
            $expired = $this->vips->expiredActiveSubscriptions();
            $count = 0;

            foreach ($expired as $subscription) {
                $this->expireSubscription($subscription);
                $count++;
            }

            return $count;
        });
    }

    private function expireSubscription(UserVip $subscription): void
    {
        $subscription->loadMissing(['level', 'user.profile']);
        $expired = $this->vips->expire($subscription);

        VipExpired::dispatch($expired);
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can manage VIP.');
        }
    }

    private function enforceRateLimit(string $key, string $action): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                $action => 'You are performing VIP actions too quickly.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
