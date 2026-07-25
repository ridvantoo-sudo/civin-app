<?php

namespace App\Features\Games\Plugins\RedEnvelope\Services;

use App\Features\Agency\Repositories\Contracts\AgencyRepository;
use App\Features\FamilyClub\Repositories\Contracts\FamilyRepository;
use App\Features\Followers\Repositories\Contracts\FollowRepository;
use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Models\RedEnvelopeClaim;
use App\Features\Games\Plugins\RedEnvelope\DTOs\ClaimRedEnvelopeDTO;
use App\Features\Games\Plugins\RedEnvelope\DTOs\CreateRedEnvelopeDTO;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeClaimed;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeCreated;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeExpired;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeFinished;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeOpened;
use App\Features\Games\Plugins\RedEnvelope\Models\RedEnvelopeConfig;
use App\Features\Games\Plugins\RedEnvelope\Repositories\Contracts\RedEnvelopeRepository;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
use App\Features\Vip\Repositories\Contracts\VipRepository;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RedEnvelopeService
{
    private const CREATE_RATE_KEY = 'red-envelope-create:%s';

    private const CLAIM_RATE_KEY = 'red-envelope-claim:%s';

    private const CREATE_RATE_MAX = 20;

    private const CLAIM_RATE_MAX = 60;

    private const RATE_DECAY_SECONDS = 60;

    /**
     * Legacy `red_envelopes.total_slots` is a TINYINT, so winner counts can
     * never exceed what that column holds regardless of admin configuration.
     */
    private const WINNER_HARD_CAP = 200;

    public function __construct(
        private RedEnvelopeRepository $envelopes,
        private WalletRepository $wallets,
        private GameRepository $games,
        private GameRng $rng,
        private FollowRepository $follows,
        private VipRepository $vips,
        private FamilyRepository $families,
        private AgencyRepository $agencies,
    ) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        $this->ensureEligible($user);

        $config = $this->requireActiveConfig();
        $createdToday = $this->envelopes->countCreatedToday($user, $config->getKey());

        return [
            'id' => $config->id,
            'slug' => $config->slug,
            'name' => $config->name,
            'description' => $config->description,
            'version' => $config->version,
            'min_coins_amount' => $config->min_coins_amount,
            'max_coins_amount' => $config->max_coins_amount,
            'min_diamonds_amount' => $config->min_diamonds_amount,
            'max_diamonds_amount' => $config->max_diamonds_amount,
            'max_winners' => min((int) $config->max_winners, self::WINNER_HARD_CAP),
            'min_duration_seconds' => $config->min_duration_seconds,
            'max_duration_seconds' => $config->max_duration_seconds,
            'default_duration_seconds' => $config->default_duration_seconds,
            'refund_unclaimed' => $config->refund_unclaimed,
            'allowed_reward_types' => $config->allowed_reward_types ?? RedEnvelope::REWARD_TYPES,
            'allowed_distribution_types' => $config->allowed_distribution_types ?? RedEnvelope::DISTRIBUTION_TYPES,
            'allowed_visibilities' => $config->allowed_visibilities ?? RedEnvelope::VISIBILITIES,
            'allowed_claim_scopes' => $config->allowed_claim_scopes ?? RedEnvelope::CLAIM_SCOPES,
            'allowed_room_types' => $config->allowed_room_types ?? RedEnvelope::ROOM_TYPES,
            'max_created_per_day' => $config->max_created_per_day,
            'created_today' => $createdToday,
            'remaining_today' => $config->max_created_per_day === null
                ? null
                : max(0, (int) $config->max_created_per_day - $createdToday),
            'status' => $config->status,
            'enabled' => $config->enabled,
            'metadata' => $config->metadata,
        ];
    }

    /**
     * @return array{envelope: RedEnvelope, wallet: Wallet, replay: bool}
     */
    public function create(User $user, CreateRedEnvelopeDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::CREATE_RATE_KEY, $user->getKey()), self::CREATE_RATE_MAX);
        $this->assertUuid($data->idempotencyKey);

        $existing = $this->envelopes->findByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            return [
                'envelope' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        $config = $this->requireActiveConfig();
        $this->assertCreateAllowed($user, $config);
        $this->validateCreateInput($config, $data);

        $game = $this->requireGame();

        try {
            $result = DB::transaction(function () use ($user, $data, $config, $game): array {
                $duplicate = RedEnvelope::query()
                    ->where('sender_id', $user->getKey())
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return [
                        'envelope' => $duplicate->loadMissing(['config', 'creator']),
                        'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                        'replay' => true,
                    ];
                }

                $wallet = $this->wallets->lockForUser($user);
                $paysCoins = $data->rewardType === RedEnvelope::REWARD_COINS;
                $balance = $paysCoins ? (int) $wallet->coins_balance : (int) $wallet->diamonds_balance;

                if ($balance < $data->totalAmount) {
                    throw ValidationException::withMessages([
                        $paysCoins ? 'coins' : 'diamonds' => 'Insufficient balance.',
                    ]);
                }

                $envelope = $this->envelopes->createEnvelope([
                    'game_id' => $game->getKey(),
                    'config_id' => $config->getKey(),
                    // Legacy columns kept in step with the plugin columns.
                    'sender_id' => $user->getKey(),
                    'creator_id' => $user->getKey(),
                    'total_coins' => $paysCoins ? $data->totalAmount : 0,
                    'remaining_coins' => $paysCoins ? $data->totalAmount : 0,
                    'total_slots' => $data->winnerCount,
                    'remaining_slots' => $data->winnerCount,
                    'total_amount' => $data->totalAmount,
                    'remaining_amount' => $data->totalAmount,
                    'winner_count' => $data->winnerCount,
                    'remaining_count' => $data->winnerCount,
                    'reward_type' => $data->rewardType,
                    'distribution_type' => $data->distributionType,
                    'visibility' => $data->visibility,
                    'claim_scope' => $data->claimScope,
                    'whitelist_user_ids' => $data->claimScope === RedEnvelope::SCOPE_WHITELIST
                        ? array_values(array_unique($data->whitelistUserIds ?? []))
                        : null,
                    'room_id' => $data->roomId,
                    'room_type' => $data->roomType,
                    'message' => $data->message,
                    'status' => RedEnvelope::STATUS_OPEN,
                    'expires_at' => now()->addSeconds($data->durationSeconds),
                    'idempotency_key' => $data->idempotencyKey,
                    'metadata' => $data->metadata === [] ? null : $data->metadata,
                ]);

                $play = $this->games->createPlay([
                    'game_id' => $game->getKey(),
                    'user_id' => $user->getKey(),
                    'bet_coins' => $paysCoins ? $data->totalAmount : 0,
                    'bet_diamonds' => $paysCoins ? 0 : $data->totalAmount,
                    'reward_coins' => 0,
                    'reward_diamonds' => 0,
                    'result' => GamePlay::RESULT_LOST,
                    'status' => GamePlay::STATUS_COMPLETED,
                    'input' => [
                        'action' => 'plugin_create',
                        'idempotency_key' => $data->idempotencyKey,
                        'reward_type' => $data->rewardType,
                        'distribution_type' => $data->distributionType,
                        'visibility' => $data->visibility,
                        'claim_scope' => $data->claimScope,
                        'total_amount' => $data->totalAmount,
                        'winner_count' => $data->winnerCount,
                        'duration_seconds' => $data->durationSeconds,
                    ],
                    'outcome' => [
                        'envelope_id' => $envelope->id,
                        'animation' => 'envelope_create',
                    ],
                    'metadata' => $data->metadata === [] ? null : $data->metadata,
                    'created_at' => now(),
                ]);

                $wallet->decrement($paysCoins ? 'coins_balance' : 'diamonds_balance', $data->totalAmount);

                $transaction = WalletTransaction::query()->create([
                    'user_id' => $user->getKey(),
                    'type' => GamePlay::WALLET_TYPE_GAME_BET,
                    'amount' => -$data->totalAmount,
                    'currency' => $this->currencyFor($data->rewardType),
                    'reference_type' => $envelope->getMorphClass(),
                    'reference_id' => $envelope->getKey(),
                    'metadata' => [
                        'game_slug' => Game::SLUG_RED_ENVELOPE,
                        'envelope_id' => $envelope->id,
                        'idempotency_key' => $data->idempotencyKey,
                        'action' => 'red_envelope_create',
                    ],
                    'created_at' => now(),
                ]);

                $envelope->forceFill([
                    'game_play_id' => $play->getKey(),
                    'wallet_transaction_id' => $transaction->getKey(),
                ])->save();

                return [
                    'envelope' => $envelope->fresh(['config', 'creator']),
                    'wallet' => $wallet->fresh(),
                    'replay' => false,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $envelope = $this->envelopes->findByIdempotency($user, $data->idempotencyKey);
            if ($envelope === null) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Duplicate create request could not be recovered.',
                ]);
            }

            return [
                'envelope' => $envelope,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        if (! $result['replay']) {
            EnvelopeCreated::dispatch($result['envelope']);
            EnvelopeOpened::dispatch($result['envelope']);
            WalletUpdated::dispatch($result['wallet']);
        }

        return $result;
    }

    /**
     * @return array{envelope: RedEnvelope, claim: RedEnvelopeClaim, wallet: Wallet, replay: bool}
     */
    public function claim(User $user, ClaimRedEnvelopeDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::CLAIM_RATE_KEY, $user->getKey()), self::CLAIM_RATE_MAX);
        $this->assertUuid($data->idempotencyKey);

        $replayClaim = $this->envelopes->findClaimByIdempotency($user, $data->idempotencyKey);
        if ($replayClaim !== null) {
            return [
                'envelope' => $replayClaim->envelope ?? $this->requireEnvelope($data->envelopeId),
                'claim' => $replayClaim,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        // Settle an elapsed timer (and refund the creator) before opening the
        // claim transaction: the refund must survive the validation error we
        // are about to throw, which would otherwise roll it back.
        $peek = $this->requireEnvelope($data->envelopeId);
        if ($peek->isExpired()) {
            $this->finish($peek->getKey());

            throw ValidationException::withMessages(['envelope_id' => 'Envelope has expired.']);
        }

        $game = $this->requireGame();

        try {
            $result = DB::transaction(function () use ($user, $data, $game): array {
                $duplicate = RedEnvelopeClaim::query()
                    ->where('user_id', $user->getKey())
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return [
                        'envelope' => $this->requireEnvelope($data->envelopeId),
                        'claim' => $duplicate,
                        'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                        'replay' => true,
                        'finished' => false,
                        'expired' => null,
                    ];
                }

                $envelope = RedEnvelope::query()->lockForUpdate()->find($data->envelopeId);
                if ($envelope === null) {
                    throw ValidationException::withMessages(['envelope_id' => 'Envelope not found.']);
                }

                if ($envelope->isExpired()) {
                    // Lost the race against the timer. Bail out; the refund is
                    // handled by finish() on the next attempt or by the scheduler.
                    throw ValidationException::withMessages(['envelope_id' => 'Envelope has expired.']);
                }

                if ($envelope->status !== RedEnvelope::STATUS_OPEN || $envelope->slotsLeft() < 1) {
                    throw ValidationException::withMessages(['envelope_id' => 'Envelope is empty or closed.']);
                }

                if ($envelope->ownerId() === $user->getKey()) {
                    throw ValidationException::withMessages(['envelope_id' => 'You cannot claim your own envelope.']);
                }

                if ($this->envelopes->findClaimFor($envelope, $user) !== null) {
                    throw ValidationException::withMessages(['envelope_id' => 'You already claimed this envelope.']);
                }

                $this->assertClaimScopeSatisfied($user, $envelope);

                $share = $this->resolveShare($envelope);
                $paysCoins = $envelope->paysCoins();

                $claim = $this->envelopes->createClaim([
                    'envelope_id' => $envelope->getKey(),
                    'user_id' => $user->getKey(),
                    'coins' => $paysCoins ? $share : 0,
                    'reward_amount' => $share,
                    'reward_type' => $envelope->rewardType(),
                    'idempotency_key' => $data->idempotencyKey,
                    'claimed_at' => now(),
                    'created_at' => now(),
                    'metadata' => $data->metadata === [] ? null : $data->metadata,
                ]);

                $remainingAmount = $envelope->amountLeft() - $share;
                $remainingCount = $envelope->slotsLeft() - 1;
                $finished = $remainingCount < 1;

                $envelope->forceFill([
                    'remaining_amount' => max(0, $remainingAmount),
                    'remaining_count' => max(0, $remainingCount),
                    'status' => $finished ? RedEnvelope::STATUS_EMPTY : RedEnvelope::STATUS_OPEN,
                ])->save();

                $wallet = $this->wallets->lockForUser($user);
                $wallet->increment($paysCoins ? 'coins_balance' : 'diamonds_balance', $share);

                $play = $this->games->createPlay([
                    'game_id' => $game->getKey(),
                    'user_id' => $user->getKey(),
                    'bet_coins' => 0,
                    'bet_diamonds' => 0,
                    'reward_coins' => $paysCoins ? $share : 0,
                    'reward_diamonds' => $paysCoins ? 0 : $share,
                    'result' => GamePlay::RESULT_CLAIMED,
                    'status' => GamePlay::STATUS_COMPLETED,
                    'input' => [
                        'action' => 'plugin_claim',
                        'idempotency_key' => $data->idempotencyKey,
                        'envelope_id' => $envelope->id,
                    ],
                    'outcome' => [
                        'envelope_id' => $envelope->id,
                        'claim_id' => $claim->id,
                        'reward_type' => $envelope->rewardType(),
                        'reward_amount' => $share,
                        'remaining_amount' => $envelope->amountLeft(),
                        'remaining_count' => $envelope->slotsLeft(),
                        'animation' => 'envelope_claim',
                    ],
                    'metadata' => $data->metadata === [] ? null : $data->metadata,
                    'created_at' => now(),
                ]);

                $transaction = WalletTransaction::query()->create([
                    'user_id' => $user->getKey(),
                    'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                    'amount' => $share,
                    'currency' => $this->currencyFor($envelope->rewardType()),
                    'reference_type' => $claim->getMorphClass(),
                    'reference_id' => $claim->getKey(),
                    'metadata' => [
                        'game_slug' => Game::SLUG_RED_ENVELOPE,
                        'envelope_id' => $envelope->id,
                        'claim_id' => $claim->id,
                        'idempotency_key' => $data->idempotencyKey,
                        'action' => 'red_envelope_claim',
                    ],
                    'created_at' => now(),
                ]);

                GameReward::query()->create([
                    'user_id' => $user->getKey(),
                    'game_id' => $game->getKey(),
                    'play_id' => $play->getKey(),
                    'coins' => $paysCoins ? $share : 0,
                    'diamonds' => $paysCoins ? 0 : $share,
                    'status' => GameReward::STATUS_GRANTED,
                    'metadata' => [
                        'envelope_id' => $envelope->id,
                        'claim_id' => $claim->id,
                        'reward_type' => $envelope->rewardType(),
                        'reward_amount' => $share,
                    ],
                ]);

                $claim->forceFill([
                    'play_id' => $play->getKey(),
                    'wallet_transaction_id' => $transaction->getKey(),
                ])->save();

                return [
                    'envelope' => $envelope->fresh(['config', 'creator']),
                    'claim' => $claim->fresh(),
                    'wallet' => $wallet->fresh(),
                    'replay' => false,
                    'finished' => $finished,
                    'expired' => null,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $claim = $this->envelopes->findClaimByIdempotency($user, $data->idempotencyKey)
                ?? $this->envelopes->findClaimFor($this->requireEnvelope($data->envelopeId), $user);

            if ($claim === null) {
                throw ValidationException::withMessages([
                    'envelope_id' => 'You already claimed this envelope.',
                ]);
            }

            return [
                'envelope' => $this->requireEnvelope($data->envelopeId),
                'claim' => $claim,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        if (! $result['replay']) {
            EnvelopeClaimed::dispatch($result['envelope'], $result['claim']);

            if ($result['finished']) {
                EnvelopeFinished::dispatch($result['envelope']);
            }

            WalletUpdated::dispatch($result['wallet']);
        }

        return [
            'envelope' => $result['envelope'],
            'claim' => $result['claim'],
            'wallet' => $result['wallet'],
            'replay' => $result['replay'],
        ];
    }

    public function history(User $user, string $type = 'all', int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->envelopes->history($user, $type, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', string $type = 'winners', int $limit = 50): Collection
    {
        return $this->envelopes->leaderboard($period, $type, $limit);
    }

    /**
     * Closes an envelope whose timer elapsed, refunding whatever was never
     * claimed. Safe to call from a scheduler; a no-op when nothing is due.
     */
    public function finish(string $envelopeId): ?RedEnvelope
    {
        $outcome = DB::transaction(function () use ($envelopeId): ?array {
            $envelope = RedEnvelope::query()->lockForUpdate()->find($envelopeId);
            if ($envelope === null || $envelope->status !== RedEnvelope::STATUS_OPEN) {
                return null;
            }

            if (! $envelope->isExpired()) {
                return null;
            }

            $refunded = $this->expireAndRefund($envelope);

            return ['envelope' => $envelope->fresh(['config', 'creator']), 'refunded' => $refunded];
        });

        if ($outcome === null) {
            return null;
        }

        EnvelopeExpired::dispatch($outcome['envelope'], $outcome['refunded']);

        return $outcome['envelope'];
    }

    /**
     * Marks an expired envelope closed and returns unclaimed value to its
     * creator. Fairness call: unclaimed value is refunded rather than burned.
     * Must be called with the envelope row already locked.
     */
    private function expireAndRefund(RedEnvelope $envelope): int
    {
        if ($envelope->status === RedEnvelope::STATUS_EXPIRED) {
            return 0;
        }

        $remaining = $envelope->amountLeft();
        $creator = $envelope->creator ?? $envelope->sender;
        $refundEnabled = $envelope->config?->refund_unclaimed ?? true;
        $refunded = 0;

        if ($remaining > 0 && $creator !== null && $refundEnabled) {
            $paysCoins = $envelope->paysCoins();
            $wallet = $this->wallets->lockForUser($creator);
            $wallet->increment($paysCoins ? 'coins_balance' : 'diamonds_balance', $remaining);
            $refunded = $remaining;

            WalletTransaction::query()->create([
                'user_id' => $creator->getKey(),
                'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                'amount' => $remaining,
                'currency' => $this->currencyFor($envelope->rewardType()),
                'reference_type' => $envelope->getMorphClass(),
                'reference_id' => $envelope->getKey(),
                'metadata' => [
                    'game_slug' => Game::SLUG_RED_ENVELOPE,
                    'envelope_id' => $envelope->id,
                    'action' => 'red_envelope_expiry_refund',
                ],
                'created_at' => now(),
            ]);
        }

        $envelope->forceFill([
            'remaining_amount' => 0,
            'remaining_count' => 0,
            'status' => RedEnvelope::STATUS_EXPIRED,
        ])->save();

        return $refunded;
    }

    /**
     * Random share, mirroring the legacy GameService allocation curve. The
     * upper bound guarantees every remaining slot can still receive at least 1,
     * so the sum of all claims can never exceed the funded total.
     */
    private function randomShare(int $remainingAmount, int $remainingCount): int
    {
        if ($remainingCount <= 1) {
            return max(1, $remainingAmount);
        }

        $min = 1;
        $max = max($min, (int) floor(($remainingAmount - ($remainingCount - 1)) * 2 / $remainingCount));
        $max = min($max, $remainingAmount - ($remainingCount - 1));

        return $this->rng->int($min, max($min, $max));
    }

    /**
     * Equal split with the remainder rolling forward, so the final claimer
     * absorbs it and the total is allocated exactly.
     */
    private function fixedShare(int $remainingAmount, int $remainingCount): int
    {
        if ($remainingCount <= 1) {
            return max(1, $remainingAmount);
        }

        return max(1, intdiv($remainingAmount, $remainingCount));
    }

    private function resolveShare(RedEnvelope $envelope): int
    {
        $remainingAmount = $envelope->amountLeft();
        $remainingCount = $envelope->slotsLeft();

        if ($remainingAmount < 1 || $remainingCount < 1) {
            throw ValidationException::withMessages(['envelope_id' => 'Envelope is empty or closed.']);
        }

        $share = $envelope->distribution_type === RedEnvelope::DIST_FIXED
            ? $this->fixedShare($remainingAmount, $remainingCount)
            : $this->randomShare($remainingAmount, $remainingCount);

        // Final guard: never allocate more than what is left, and always leave
        // at least one unit per remaining slot.
        return max(1, min($share, $remainingAmount - ($remainingCount - 1)));
    }

    private function assertClaimScopeSatisfied(User $claimer, RedEnvelope $envelope): void
    {
        $scope = $envelope->claim_scope ?? RedEnvelope::SCOPE_EVERYONE;
        $creator = $envelope->creator ?? $envelope->sender;

        $allowed = match ($scope) {
            RedEnvelope::SCOPE_EVERYONE => true,
            RedEnvelope::SCOPE_FOLLOWERS => $creator !== null && $this->follows->isFollowing($claimer, $creator),
            RedEnvelope::SCOPE_VIP => $this->vips->findForUser($claimer)?->isActive() ?? false,
            RedEnvelope::SCOPE_FAMILY => $this->sharesGroup(
                $this->families->findApprovedMembershipForUser($claimer)?->family_id,
                $creator === null ? null : $this->families->findApprovedMembershipForUser($creator)?->family_id,
            ),
            RedEnvelope::SCOPE_AGENCY => $this->sharesGroup(
                $this->agencies->findApprovedMembershipForUser($claimer)?->agency_id,
                $creator === null ? null : $this->agencies->findApprovedMembershipForUser($creator)?->agency_id,
            ),
            RedEnvelope::SCOPE_WHITELIST => in_array(
                (string) $claimer->getKey(),
                array_map('strval', $envelope->whitelist_user_ids ?? []),
                true,
            ),
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages([
                'envelope_id' => 'You are not eligible to claim this envelope.',
                'claim_scope' => $scope,
            ]);
        }
    }

    private function sharesGroup(?string $claimerGroupId, ?string $creatorGroupId): bool
    {
        return $claimerGroupId !== null && $claimerGroupId === $creatorGroupId;
    }

    private function validateCreateInput(RedEnvelopeConfig $config, CreateRedEnvelopeDTO $data): void
    {
        if (! in_array($data->rewardType, RedEnvelope::REWARD_TYPES, true) || ! $config->rewardTypeAllowed($data->rewardType)) {
            throw ValidationException::withMessages(['reward_type' => 'This reward type is not available.']);
        }

        if (! in_array($data->distributionType, RedEnvelope::DISTRIBUTION_TYPES, true)
            || ! $config->distributionTypeAllowed($data->distributionType)) {
            throw ValidationException::withMessages(['distribution_type' => 'This distribution type is not available.']);
        }

        if (! in_array($data->visibility, RedEnvelope::VISIBILITIES, true) || ! $config->visibilityAllowed($data->visibility)) {
            throw ValidationException::withMessages(['visibility' => 'This visibility is not available.']);
        }

        if (! in_array($data->claimScope, RedEnvelope::CLAIM_SCOPES, true) || ! $config->claimScopeAllowed($data->claimScope)) {
            throw ValidationException::withMessages(['claim_scope' => 'This claim scope is not available.']);
        }

        $minAmount = $config->minAmountFor($data->rewardType);
        $maxAmount = $config->maxAmountFor($data->rewardType);
        if ($data->totalAmount < $minAmount || $data->totalAmount > $maxAmount) {
            throw ValidationException::withMessages([
                'total_amount' => "Total amount must be between {$minAmount} and {$maxAmount}.",
            ]);
        }

        $maxWinners = min((int) $config->max_winners, self::WINNER_HARD_CAP);
        if ($data->winnerCount < 1 || $data->winnerCount > $maxWinners) {
            throw ValidationException::withMessages([
                'winner_count' => "Winner count must be between 1 and {$maxWinners}.",
            ]);
        }

        if ($data->totalAmount < $data->winnerCount) {
            throw ValidationException::withMessages([
                'total_amount' => 'Total amount must be at least one unit per winner.',
            ]);
        }

        if ($data->durationSeconds < (int) $config->min_duration_seconds
            || $data->durationSeconds > (int) $config->max_duration_seconds) {
            throw ValidationException::withMessages([
                'duration_seconds' => "Duration must be between {$config->min_duration_seconds} and {$config->max_duration_seconds} seconds.",
            ]);
        }

        if ($data->roomType !== null && ! $config->roomTypeAllowed($data->roomType)) {
            throw ValidationException::withMessages(['room_type' => 'This room type is not available.']);
        }

        $needsRoom = $data->roomType !== null
            || in_array($data->visibility, [RedEnvelope::VIS_LIVE_ROOM, RedEnvelope::VIS_VOICE_ROOM], true);

        if ($needsRoom && ($data->roomId === null || $data->roomId === '')) {
            throw ValidationException::withMessages(['room_id' => 'Room envelopes require a room id.']);
        }

        if ($data->roomId !== null && $data->roomType === null) {
            throw ValidationException::withMessages(['room_type' => 'Room envelopes require a room type.']);
        }

        if ($data->claimScope === RedEnvelope::SCOPE_WHITELIST && ($data->whitelistUserIds ?? []) === []) {
            throw ValidationException::withMessages([
                'whitelist_user_ids' => 'Whitelist envelopes require at least one user id.',
            ]);
        }
    }

    private function assertCreateAllowed(User $user, RedEnvelopeConfig $config): void
    {
        if ($config->max_created_per_day === null) {
            return;
        }

        if ($this->envelopes->countCreatedToday($user, $config->getKey()) >= (int) $config->max_created_per_day) {
            throw ValidationException::withMessages(['create' => 'Daily red envelope limit reached.']);
        }
    }

    private function requireActiveConfig(): RedEnvelopeConfig
    {
        $config = $this->envelopes->activeConfig();
        if ($config === null || ! $config->isActive()) {
            throw ValidationException::withMessages(['config' => 'Red Envelope is not configured.']);
        }

        return $config;
    }

    private function requireGame(): Game
    {
        $game = Game::query()->where('slug', Game::SLUG_RED_ENVELOPE)->first();
        if ($game === null || ! $game->isActive()) {
            throw ValidationException::withMessages(['game' => 'Red Envelope game is unavailable.']);
        }

        return $game;
    }

    private function requireEnvelope(string $envelopeId): RedEnvelope
    {
        $envelope = $this->envelopes->findEnvelope($envelopeId);
        if ($envelope === null) {
            throw ValidationException::withMessages(['envelope_id' => 'Envelope not found.']);
        }

        return $envelope;
    }

    private function currencyFor(string $rewardType): string
    {
        return $rewardType === RedEnvelope::REWARD_DIAMONDS
            ? WalletTransaction::CURRENCY_DIAMONDS
            : WalletTransaction::CURRENCY_COINS;
    }

    private function assertUuid(string $idempotencyKey): void
    {
        if (! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency key must be a valid UUID.',
            ]);
        }
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== User::STATUS_ACTIVE) {
            throw new AuthorizationException('Only active registered users can use Red Envelopes.');
        }
    }

    private function enforceRateLimit(string $key, int $max): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'rate_limit' => 'You are sending requests too quickly. Please wait a moment.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
