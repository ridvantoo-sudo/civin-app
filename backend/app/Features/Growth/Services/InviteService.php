<?php

namespace App\Features\Growth\Services;

use App\Features\Growth\DTOs\CreateInviteData;
use App\Features\Growth\Events\InviteAccepted;
use App\Features\Growth\Models\Invite;
use App\Features\Growth\Models\InviteReward;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class InviteService
{
    private const CREATE_RATE_KEY = 'growth-invite-create:%s';

    public function __construct(private WalletRepository $wallets) {}

    public function create(User $inviter, CreateInviteData $data): Invite
    {
        $this->ensureEligible($inviter);
        $this->enforceRateLimit(sprintf(self::CREATE_RATE_KEY, $inviter->getKey()));

        $channel = $data->channel === Invite::CHANNEL_EMAIL ? Invite::CHANNEL_EMAIL : Invite::CHANNEL_LINK;
        if ($channel === Invite::CHANNEL_EMAIL && ($data->target === null || ! filter_var($data->target, FILTER_VALIDATE_EMAIL))) {
            throw ValidationException::withMessages(['target' => 'A valid email is required for email invites.']);
        }

        $maxPending = (int) config('growth.invite.max_pending_per_user', 50);
        $pending = Invite::query()
            ->where('inviter_id', $inviter->getKey())
            ->where('status', Invite::STATUS_PENDING)
            ->count();

        if ($pending >= $maxPending) {
            throw ValidationException::withMessages(['invite' => 'You have too many pending invites.']);
        }

        $expiresDays = (int) config('growth.invite.expires_days', 30);

        return Invite::query()->create([
            'inviter_id' => $inviter->getKey(),
            'token' => $this->uniqueToken(),
            'channel' => $channel,
            'target' => $channel === Invite::CHANNEL_EMAIL ? strtolower((string) $data->target) : null,
            'status' => Invite::STATUS_PENDING,
            'expires_at' => $expiresDays > 0 ? now()->addDays($expiresDays) : null,
            'metadata' => $data->metadata,
        ]);
    }

    public function accept(User $invitee, string $token): Invite
    {
        $this->ensureEligible($invitee);

        $invite = Invite::query()->where('token', $token)->first();
        if ($invite === null) {
            throw ValidationException::withMessages(['token' => 'Invite not found.']);
        }

        if ($invite->inviter_id === $invitee->getKey()) {
            throw ValidationException::withMessages(['token' => 'You cannot accept your own invite.']);
        }

        if (! $invite->isAcceptable()) {
            if ($invite->expires_at !== null && $invite->expires_at->isPast() && $invite->status === Invite::STATUS_PENDING) {
                $invite->forceFill(['status' => Invite::STATUS_EXPIRED])->save();
            }
            throw ValidationException::withMessages(['token' => 'This invite is no longer valid.']);
        }

        // One accepted invite reward per invitee.
        if (Invite::query()->where('accepted_by', $invitee->getKey())->exists()) {
            throw ValidationException::withMessages(['token' => 'You already accepted an invite.']);
        }

        $inviterCoins = (int) config('growth.invite.inviter_coins', 150);
        $inviteeCoins = (int) config('growth.invite.invitee_coins', 75);
        $inviterDiamonds = (int) config('growth.invite.inviter_diamonds', 0);
        $inviteeDiamonds = (int) config('growth.invite.invitee_diamonds', 0);

        $invite = DB::transaction(function () use (
            $invite,
            $invitee,
            $inviterCoins,
            $inviteeCoins,
            $inviterDiamonds,
            $inviteeDiamonds,
        ): Invite {
            $locked = Invite::query()->lockForUpdate()->findOrFail($invite->getKey());
            if (! $locked->isAcceptable()) {
                throw ValidationException::withMessages(['token' => 'This invite is no longer valid.']);
            }

            $locked->forceFill([
                'status' => Invite::STATUS_ACCEPTED,
                'accepted_by' => $invitee->getKey(),
                'accepted_at' => now(),
            ])->save();

            $inviter = User::query()->findOrFail($locked->inviter_id);

            $inviterReward = InviteReward::query()->create([
                'invite_id' => $locked->getKey(),
                'user_id' => $inviter->getKey(),
                'role' => InviteReward::ROLE_INVITER,
                'coins' => $inviterCoins,
                'diamonds' => $inviterDiamonds,
                'status' => InviteReward::STATUS_GRANTED,
                'granted_at' => now(),
            ]);

            $inviteeReward = InviteReward::query()->create([
                'invite_id' => $locked->getKey(),
                'user_id' => $invitee->getKey(),
                'role' => InviteReward::ROLE_INVITEE,
                'coins' => $inviteeCoins,
                'diamonds' => $inviteeDiamonds,
                'status' => InviteReward::STATUS_GRANTED,
                'granted_at' => now(),
            ]);

            WalletUpdated::dispatch($this->wallets->applyGrowthReward(
                $inviter,
                $inviterReward,
                $inviterCoins,
                $inviterDiamonds,
                ['kind' => 'invite_inviter'],
            ));
            WalletUpdated::dispatch($this->wallets->applyGrowthReward(
                $invitee,
                $inviteeReward,
                $inviteeCoins,
                $inviteeDiamonds,
                ['kind' => 'invite_invitee'],
            ));

            return $locked->fresh(['rewards']) ?? $locked;
        });

        InviteAccepted::dispatch($invite);

        return $invite;
    }

    public function mine(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return Invite::query()
            ->where('inviter_id', $user->getKey())
            ->latest()
            ->paginate(max(1, min($perPage, 100)));
    }

    private function uniqueToken(): string
    {
        $length = max(16, (int) config('growth.invite.token_length', 24));

        do {
            $token = Str::lower(Str::random($length));
        } while (Invite::query()->where('token', $token)->exists());

        return $token;
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use invites.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw ValidationException::withMessages(['invite' => 'You are creating invites too quickly.']);
        }

        RateLimiter::hit($key, 60);
    }
}
