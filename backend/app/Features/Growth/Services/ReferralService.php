<?php

namespace App\Features\Growth\Services;

use App\Features\Growth\DTOs\AttachReferralData;
use App\Features\Growth\Events\ReferralAttached;
use App\Features\Growth\Models\ReferralAttribution;
use App\Features\Growth\Models\ReferralCode;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ReferralService
{
    private const ATTACH_RATE_KEY = 'growth-referral-attach:%s';

    public function __construct(private WalletRepository $wallets) {}

    public function myCode(User $user): ReferralCode
    {
        $this->ensureEligible($user);

        $existing = ReferralCode::query()->where('user_id', $user->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        $maxUses = (int) config('growth.referral.max_uses_per_code', 0);

        return ReferralCode::query()->create([
            'user_id' => $user->getKey(),
            'code' => $this->uniqueCode(),
            'max_uses' => $maxUses > 0 ? $maxUses : null,
            'active' => true,
        ]);
    }

    public function attach(User $invitee, AttachReferralData $data): ReferralAttribution
    {
        $this->ensureEligible($invitee);
        $this->enforceRateLimit(sprintf(self::ATTACH_RATE_KEY, $invitee->getKey()));

        if (ReferralAttribution::query()->where('invitee_id', $invitee->getKey())->exists()) {
            throw ValidationException::withMessages(['code' => 'You already used a referral code.']);
        }

        $windowDays = (int) config('growth.referral.attach_window_days', 7);
        if ($windowDays > 0 && $invitee->created_at !== null && $invitee->created_at->lt(now()->subDays($windowDays))) {
            throw ValidationException::withMessages(['code' => 'Referral attach window has expired.']);
        }

        $code = ReferralCode::query()
            ->where('code', strtoupper(trim($data->code)))
            ->where('active', true)
            ->first();

        if ($code === null) {
            throw ValidationException::withMessages(['code' => 'Invalid referral code.']);
        }

        if ($code->user_id === $invitee->getKey()) {
            throw ValidationException::withMessages(['code' => 'You cannot use your own referral code.']);
        }

        if (! $code->hasRemainingUses()) {
            throw ValidationException::withMessages(['code' => 'This referral code has reached its limit.']);
        }

        $referrerCoins = (int) config('growth.referral.referrer_coins', 100);
        $inviteeCoins = (int) config('growth.referral.invitee_coins', 50);
        $referrerDiamonds = (int) config('growth.referral.referrer_diamonds', 0);
        $inviteeDiamonds = (int) config('growth.referral.invitee_diamonds', 0);

        $attribution = DB::transaction(function () use (
            $invitee,
            $code,
            $referrerCoins,
            $inviteeCoins,
            $referrerDiamonds,
            $inviteeDiamonds,
        ): ReferralAttribution {
            $locked = ReferralCode::query()->lockForUpdate()->findOrFail($code->getKey());
            if (! $locked->hasRemainingUses()) {
                throw ValidationException::withMessages(['code' => 'This referral code has reached its limit.']);
            }

            $attribution = ReferralAttribution::query()->create([
                'referrer_id' => $locked->user_id,
                'invitee_id' => $invitee->getKey(),
                'referral_code_id' => $locked->getKey(),
                'status' => ReferralAttribution::STATUS_REWARDED,
                'referrer_coins' => $referrerCoins,
                'invitee_coins' => $inviteeCoins,
                'referrer_diamonds' => $referrerDiamonds,
                'invitee_diamonds' => $inviteeDiamonds,
                'rewarded_at' => now(),
            ]);

            $locked->increment('uses_count');

            $referrer = User::query()->findOrFail($locked->user_id);

            $referrerWallet = $this->wallets->applyGrowthReward(
                $referrer,
                $attribution,
                $referrerCoins,
                $referrerDiamonds,
                ['kind' => 'referral_referrer'],
            );
            $inviteeWallet = $this->wallets->applyGrowthReward(
                $invitee,
                $attribution,
                $inviteeCoins,
                $inviteeDiamonds,
                ['kind' => 'referral_invitee'],
            );

            WalletUpdated::dispatch($referrerWallet);
            WalletUpdated::dispatch($inviteeWallet);

            return $attribution;
        });

        ReferralAttached::dispatch($attribution);

        return $attribution->load(['code', 'referrer.profile']);
    }

    public function stats(User $user): array
    {
        $this->ensureEligible($user);
        $code = $this->myCode($user);

        return [
            'code' => $code->code,
            'uses_count' => $code->uses_count,
            'max_uses' => $code->max_uses,
            'successful_referrals' => ReferralAttribution::query()
                ->where('referrer_id', $user->getKey())
                ->where('status', ReferralAttribution::STATUS_REWARDED)
                ->count(),
        ];
    }

    private function uniqueCode(): string
    {
        $length = max(6, (int) config('growth.referral.code_length', 8));

        do {
            $code = strtoupper(Str::random($length));
        } while (ReferralCode::query()->where('code', $code)->exists());

        return $code;
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use referrals.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['code' => 'Too many referral attempts. Try again later.']);
        }

        RateLimiter::hit($key, 60);
    }
}
