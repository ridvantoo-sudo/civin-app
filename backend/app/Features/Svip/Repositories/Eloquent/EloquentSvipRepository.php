<?php

namespace App\Features\Svip\Repositories\Eloquent;

use App\Features\Profiles\Models\Profile;
use App\Features\Svip\DTOs\PurchaseSvipData;
use App\Features\Svip\DTOs\UpgradeSvipData;
use App\Features\Svip\Models\SvipLevel;
use App\Features\Svip\Models\SvipTransaction;
use App\Features\Svip\Models\UserSvip;
use App\Features\Svip\Repositories\Contracts\SvipRepository;
use App\Features\Users\Models\User;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Repositories\Contracts\VipRepository;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentSvipRepository implements SvipRepository
{
    public function __construct(
        private readonly WalletRepository $wallets,
        private readonly VipRepository $vips,
    ) {}

    public function activeLevels(): Collection
    {
        return SvipLevel::query()
            ->where('status', SvipLevel::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('level')
            ->get();
    }

    public function findActiveLevel(string $svipLevelId): ?SvipLevel
    {
        return SvipLevel::query()
            ->whereKey($svipLevelId)
            ->where('status', SvipLevel::STATUS_ACTIVE)
            ->first();
    }

    public function findForUser(User $user): ?UserSvip
    {
        return UserSvip::query()
            ->where('user_id', $user->getKey())
            ->with('level')
            ->first();
    }

    public function lockForUser(User $user): ?UserSvip
    {
        return UserSvip::query()
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();
    }

    public function purchase(User $user, SvipLevel $level, PurchaseSvipData $data): UserSvip
    {
        return DB::transaction(function () use ($user, $level, $data): UserSvip {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $existing = $this->lockForUser($lockedUser);

            if ($existing !== null && $existing->isActive()) {
                throw ValidationException::withMessages([
                    'svip_level_id' => 'You already have an active SVIP subscription. Use upgrade instead.',
                ]);
            }

            if ($existing !== null) {
                $existing->delete();
            }

            $this->supersedeActiveVip($lockedUser);

            $wallet = $this->wallets->lockForUser($lockedUser);

            if ($wallet->coins_balance < $level->coin_price) {
                throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
            }

            $now = now();
            $subscription = UserSvip::query()->create([
                'user_id' => $lockedUser->getKey(),
                'svip_level_id' => $level->getKey(),
                'status' => UserSvip::STATUS_ACTIVE,
                'started_at' => $now,
                'expires_at' => $now->copy()->addDays($level->duration_days),
            ]);

            $transaction = SvipTransaction::query()->create([
                'user_id' => $lockedUser->getKey(),
                'svip_level_id' => $level->getKey(),
                'user_svip_id' => $subscription->getKey(),
                'type' => SvipTransaction::TYPE_PURCHASE,
                'coins' => $level->coin_price,
                'from_level' => null,
                'to_level' => $level->level,
                'metadata' => $data->metadata === null || $data->metadata === [] ? null : $data->metadata,
                'created_at' => $now,
            ]);

            $wallet->decrement('coins_balance', $level->coin_price);

            WalletTransaction::query()->create([
                'user_id' => $lockedUser->getKey(),
                'type' => SvipTransaction::WALLET_TYPE_SVIP_PURCHASE,
                'amount' => -$level->coin_price,
                'currency' => WalletTransaction::CURRENCY_COINS,
                'reference_type' => $transaction->getMorphClass(),
                'reference_id' => $transaction->getKey(),
                'metadata' => [
                    'svip_level_id' => $level->getKey(),
                    'svip_level' => $level->level,
                    'action' => SvipTransaction::TYPE_PURCHASE,
                ],
                'created_at' => $now,
            ]);

            $this->markProfileSvip($lockedUser, true);

            return $subscription->fresh(['level', 'user.profile']);
        });
    }

    public function upgrade(User $user, UserSvip $subscription, SvipLevel $level, UpgradeSvipData $data): UserSvip
    {
        return DB::transaction(function () use ($user, $subscription, $level, $data): UserSvip {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $locked = UserSvip::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $locked->loadMissing('level');

            if ($locked->user_id !== $lockedUser->getKey()) {
                throw ValidationException::withMessages(['svip' => 'SVIP subscription not found.']);
            }

            if (! $locked->isActive()) {
                throw ValidationException::withMessages([
                    'svip' => 'You do not have an active SVIP subscription to upgrade.',
                ]);
            }

            $currentLevel = $locked->level;
            if ($currentLevel === null) {
                throw ValidationException::withMessages(['svip' => 'Current SVIP level is unavailable.']);
            }

            if ($level->level <= $currentLevel->level) {
                throw ValidationException::withMessages([
                    'svip_level_id' => 'You can only upgrade to a higher SVIP level.',
                ]);
            }

            $coins = $level->coin_price - $currentLevel->coin_price;
            if ($coins < 1) {
                throw ValidationException::withMessages([
                    'svip_level_id' => 'Upgrade price must be greater than your current SVIP level.',
                ]);
            }

            $wallet = $this->wallets->lockForUser($lockedUser);

            if ($wallet->coins_balance < $coins) {
                throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
            }

            $now = now();

            $locked->forceFill([
                'svip_level_id' => $level->getKey(),
                'status' => UserSvip::STATUS_ACTIVE,
            ])->save();

            $transaction = SvipTransaction::query()->create([
                'user_id' => $lockedUser->getKey(),
                'svip_level_id' => $level->getKey(),
                'user_svip_id' => $locked->getKey(),
                'type' => SvipTransaction::TYPE_UPGRADE,
                'coins' => $coins,
                'from_level' => $currentLevel->level,
                'to_level' => $level->level,
                'metadata' => $data->metadata === null || $data->metadata === [] ? null : $data->metadata,
                'created_at' => $now,
            ]);

            $wallet->decrement('coins_balance', $coins);

            WalletTransaction::query()->create([
                'user_id' => $lockedUser->getKey(),
                'type' => SvipTransaction::WALLET_TYPE_SVIP_PURCHASE,
                'amount' => -$coins,
                'currency' => WalletTransaction::CURRENCY_COINS,
                'reference_type' => $transaction->getMorphClass(),
                'reference_id' => $transaction->getKey(),
                'metadata' => [
                    'svip_level_id' => $level->getKey(),
                    'from_level' => $currentLevel->level,
                    'to_level' => $level->level,
                    'action' => SvipTransaction::TYPE_UPGRADE,
                ],
                'created_at' => $now,
            ]);

            $this->markProfileSvip($lockedUser, true);

            return $locked->fresh(['level', 'user.profile']);
        });
    }

    public function expiredActiveSubscriptions(): Collection
    {
        return UserSvip::query()
            ->where('status', UserSvip::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->with(['level', 'user.profile'])
            ->get();
    }

    public function expire(UserSvip $subscription): UserSvip
    {
        return DB::transaction(function () use ($subscription): UserSvip {
            $locked = UserSvip::query()
                ->with(['level', 'user.profile'])
                ->lockForUpdate()
                ->find($subscription->getKey());

            if ($locked === null) {
                $subscription->status = UserSvip::STATUS_EXPIRED;

                return $subscription;
            }

            $userId = $locked->user_id;
            $payload = $locked->replicate();
            $payload->id = $locked->getKey();
            $payload->status = UserSvip::STATUS_EXPIRED;
            $payload->setRelation('level', $locked->level);
            $payload->setRelation('user', $locked->user);
            $payload->exists = false;

            $locked->delete();
            $this->markProfileSvipById($userId, false);

            return $payload;
        });
    }

    private function supersedeActiveVip(User $user): void
    {
        $vip = UserVip::query()
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();

        if ($vip === null) {
            return;
        }

        if ($vip->isActive()) {
            $this->vips->expire($vip);
        } else {
            $vip->delete();
            Profile::query()
                ->where('user_id', $user->getKey())
                ->update(['is_vip' => false]);
        }
    }

    private function markProfileSvip(User $user, bool $isSvip): void
    {
        $this->markProfileSvipById($user->getKey(), $isSvip);
    }

    private function markProfileSvipById(string $userId, bool $isSvip): void
    {
        Profile::query()
            ->where('user_id', $userId)
            ->update(['is_svip' => $isSvip]);
    }
}
