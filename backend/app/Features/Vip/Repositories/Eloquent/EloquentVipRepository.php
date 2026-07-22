<?php

namespace App\Features\Vip\Repositories\Eloquent;

use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use App\Features\Vip\DTOs\PurchaseVipData;
use App\Features\Vip\DTOs\UpgradeVipData;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Models\VipLevel;
use App\Features\Vip\Models\VipTransaction;
use App\Features\Vip\Repositories\Contracts\VipRepository;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentVipRepository implements VipRepository
{
    public function __construct(private readonly WalletRepository $wallets) {}

    public function activeLevels(): Collection
    {
        return VipLevel::query()
            ->where('status', VipLevel::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('level')
            ->get();
    }

    public function findActiveLevel(string $vipLevelId): ?VipLevel
    {
        return VipLevel::query()
            ->whereKey($vipLevelId)
            ->where('status', VipLevel::STATUS_ACTIVE)
            ->first();
    }

    public function findForUser(User $user): ?UserVip
    {
        return UserVip::query()
            ->where('user_id', $user->getKey())
            ->with('level')
            ->first();
    }

    public function lockForUser(User $user): ?UserVip
    {
        return UserVip::query()
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();
    }

    public function purchase(User $user, VipLevel $level, PurchaseVipData $data): UserVip
    {
        return DB::transaction(function () use ($user, $level, $data): UserVip {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $existing = $this->lockForUser($lockedUser);

            if ($existing !== null && $existing->isActive()) {
                throw ValidationException::withMessages([
                    'vip_level_id' => 'You already have an active VIP subscription. Use upgrade instead.',
                ]);
            }

            if ($existing !== null) {
                $existing->delete();
            }

            $wallet = $this->wallets->lockForUser($lockedUser);

            if ($wallet->coins_balance < $level->coin_price) {
                throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
            }

            $now = now();
            $subscription = UserVip::query()->create([
                'user_id' => $lockedUser->getKey(),
                'vip_level_id' => $level->getKey(),
                'status' => UserVip::STATUS_ACTIVE,
                'started_at' => $now,
                'expires_at' => $now->copy()->addDays($level->duration_days),
            ]);

            $transaction = VipTransaction::query()->create([
                'user_id' => $lockedUser->getKey(),
                'vip_level_id' => $level->getKey(),
                'user_vip_id' => $subscription->getKey(),
                'type' => VipTransaction::TYPE_PURCHASE,
                'coins' => $level->coin_price,
                'from_level' => null,
                'to_level' => $level->level,
                'metadata' => $data->metadata === null || $data->metadata === [] ? null : $data->metadata,
                'created_at' => $now,
            ]);

            $wallet->decrement('coins_balance', $level->coin_price);

            WalletTransaction::query()->create([
                'user_id' => $lockedUser->getKey(),
                'type' => VipTransaction::WALLET_TYPE_VIP_PURCHASE,
                'amount' => -$level->coin_price,
                'currency' => WalletTransaction::CURRENCY_COINS,
                'reference_type' => $transaction->getMorphClass(),
                'reference_id' => $transaction->getKey(),
                'metadata' => [
                    'vip_level_id' => $level->getKey(),
                    'vip_level' => $level->level,
                    'action' => VipTransaction::TYPE_PURCHASE,
                ],
                'created_at' => $now,
            ]);

            $this->markProfileVip($lockedUser, true);

            return $subscription->fresh(['level', 'user.profile']);
        });
    }

    public function upgrade(User $user, UserVip $subscription, VipLevel $level, UpgradeVipData $data): UserVip
    {
        return DB::transaction(function () use ($user, $subscription, $level, $data): UserVip {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $locked = UserVip::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $locked->loadMissing('level');

            if ($locked->user_id !== $lockedUser->getKey()) {
                throw ValidationException::withMessages(['vip' => 'VIP subscription not found.']);
            }

            if (! $locked->isActive()) {
                throw ValidationException::withMessages([
                    'vip' => 'You do not have an active VIP subscription to upgrade.',
                ]);
            }

            $currentLevel = $locked->level;
            if ($currentLevel === null) {
                throw ValidationException::withMessages(['vip' => 'Current VIP level is unavailable.']);
            }

            if ($level->level <= $currentLevel->level) {
                throw ValidationException::withMessages([
                    'vip_level_id' => 'You can only upgrade to a higher VIP level.',
                ]);
            }

            $coins = $level->coin_price - $currentLevel->coin_price;
            if ($coins < 1) {
                throw ValidationException::withMessages([
                    'vip_level_id' => 'Upgrade price must be greater than your current VIP level.',
                ]);
            }

            $wallet = $this->wallets->lockForUser($lockedUser);

            if ($wallet->coins_balance < $coins) {
                throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
            }

            $now = now();

            $locked->forceFill([
                'vip_level_id' => $level->getKey(),
                'status' => UserVip::STATUS_ACTIVE,
            ])->save();

            $transaction = VipTransaction::query()->create([
                'user_id' => $lockedUser->getKey(),
                'vip_level_id' => $level->getKey(),
                'user_vip_id' => $locked->getKey(),
                'type' => VipTransaction::TYPE_UPGRADE,
                'coins' => $coins,
                'from_level' => $currentLevel->level,
                'to_level' => $level->level,
                'metadata' => $data->metadata === null || $data->metadata === [] ? null : $data->metadata,
                'created_at' => $now,
            ]);

            $wallet->decrement('coins_balance', $coins);

            WalletTransaction::query()->create([
                'user_id' => $lockedUser->getKey(),
                'type' => VipTransaction::WALLET_TYPE_VIP_PURCHASE,
                'amount' => -$coins,
                'currency' => WalletTransaction::CURRENCY_COINS,
                'reference_type' => $transaction->getMorphClass(),
                'reference_id' => $transaction->getKey(),
                'metadata' => [
                    'vip_level_id' => $level->getKey(),
                    'from_level' => $currentLevel->level,
                    'to_level' => $level->level,
                    'action' => VipTransaction::TYPE_UPGRADE,
                ],
                'created_at' => $now,
            ]);

            $this->markProfileVip($lockedUser, true);

            return $locked->fresh(['level', 'user.profile']);
        });
    }

    public function expiredActiveSubscriptions(): Collection
    {
        return UserVip::query()
            ->where('status', UserVip::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->with(['level', 'user.profile'])
            ->get();
    }

    public function expire(UserVip $subscription): UserVip
    {
        return DB::transaction(function () use ($subscription): UserVip {
            $locked = UserVip::query()
                ->with(['level', 'user.profile'])
                ->lockForUpdate()
                ->find($subscription->getKey());

            if ($locked === null) {
                $subscription->status = UserVip::STATUS_EXPIRED;

                return $subscription;
            }

            $locked->status = UserVip::STATUS_EXPIRED;
            $userId = $locked->user_id;
            $payload = $locked->replicate();
            $payload->id = $locked->getKey();
            $payload->status = UserVip::STATUS_EXPIRED;
            $payload->setRelation('level', $locked->level);
            $payload->setRelation('user', $locked->user);
            $payload->exists = false;

            $locked->delete();
            $this->markProfileVipById($userId, false);

            return $payload;
        });
    }

    private function markProfileVip(User $user, bool $isVip): void
    {
        $this->markProfileVipById($user->getKey(), $isVip);
    }

    private function markProfileVipById(string $userId, bool $isVip): void
    {
        Profile::query()
            ->where('user_id', $userId)
            ->update(['is_vip' => $isVip]);
    }
}
