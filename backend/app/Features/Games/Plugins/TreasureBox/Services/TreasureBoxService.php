<?php

namespace App\Features\Games\Plugins\TreasureBox\Services;

use App\Features\Agency\Repositories\Contracts\AgencyRepository;
use App\Features\FamilyClub\Repositories\Contracts\FamilyRepository;
use App\Features\Followers\Repositories\Contracts\FollowRepository;
use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Models\TreasureBoxParticipant;
use App\Features\Games\Models\TreasureBoxWinner;
use App\Features\Games\Plugins\TreasureBox\DTOs\CreateTreasureBoxDTO;
use App\Features\Games\Plugins\TreasureBox\DTOs\JoinTreasureBoxDTO;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureCountdownStarted;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureCreated;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureExpired;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureFinished;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureJoined;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureOpened;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureRewarded;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureScheduled;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureWinnerSelected;
use App\Features\Games\Plugins\TreasureBox\Models\TreasureBoxConfig;
use App\Features\Games\Plugins\TreasureBox\Repositories\Contracts\TreasureBoxRepository;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Games\Services\GameRng;
use App\Features\Svip\Repositories\Contracts\SvipRepository;
use App\Features\Users\Models\User;
use App\Features\Vip\Repositories\Contracts\VipRepository;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Treasure Box production service.
 *
 * Non-wallet rewards (gift/frame/entry_effect/vip_days/svip_days/mystery/jackpot)
 * are still funded by the creator in coins: the cost equals total_amount.
 * Winners receive GameReward rows with STATUS_PENDING_FULFILLMENT.
 */
final readonly class TreasureBoxService
{
    private const CREATE_RATE_KEY = 'treasure-box-create:%s';

    private const JOIN_RATE_KEY = 'treasure-box-join:%s';

    private const CREATE_RATE_MAX = 20;

    private const JOIN_RATE_MAX = 60;

    private const RATE_DECAY_SECONDS = 60;

    private const WINNER_HARD_CAP = 200;

    public function __construct(
        private TreasureBoxRepository $treasures,
        private WalletRepository $wallets,
        private GameRepository $games,
        private GameRng $rng,
        private FollowRepository $follows,
        private VipRepository $vips,
        private SvipRepository $svips,
        private FamilyRepository $families,
        private AgencyRepository $agencies,
    ) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        $this->ensureEligible($user);

        $config = $this->requireActiveConfig();
        $createdToday = $this->treasures->countCreatedToday($user, $config->getKey());

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
            'min_countdown_seconds' => $config->min_countdown_seconds,
            'max_countdown_seconds' => $config->max_countdown_seconds,
            'default_countdown_seconds' => $config->default_countdown_seconds,
            'min_claim_duration_seconds' => $config->min_claim_duration_seconds,
            'max_claim_duration_seconds' => $config->max_claim_duration_seconds,
            'default_claim_duration_seconds' => $config->default_claim_duration_seconds,
            'refund_unclaimed' => $config->refund_unclaimed,
            'allowed_reward_types' => $config->allowed_reward_types ?? TreasureBox::REWARD_TYPES,
            'allowed_types' => $config->allowed_types ?? TreasureBox::TYPES,
            'allowed_visibilities' => $config->allowed_visibilities ?? TreasureBox::VISIBILITIES,
            'allowed_eligibilities' => $config->allowed_eligibilities ?? TreasureBox::ELIGIBILITY_SCOPES,
            'allowed_room_types' => $config->allowed_room_types ?? TreasureBox::ROOM_TYPES,
            'allowed_distribution_types' => TreasureBox::DISTRIBUTION_TYPES,
            'auto_rules' => $config->auto_rules,
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
     * @return array{treasure: TreasureBox, wallet: Wallet, replay: bool}
     */
    public function create(User $user, CreateTreasureBoxDTO $data): array
    {
        $this->ensureEligible($user);
        $isAutoRule = ($data->metadata['source'] ?? null) === 'auto_rule';
        if (! $isAutoRule) {
            $this->enforceRateLimit(sprintf(self::CREATE_RATE_KEY, $user->getKey()), self::CREATE_RATE_MAX);
        }
        $this->assertUuid($data->idempotencyKey);

        $existing = $this->treasures->findByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            return [
                'treasure' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        $config = $this->requireActiveConfig();
        $this->assertCreateAllowed($user, $config);
        $this->validateCreateInput($config, $data);

        $game = $this->requireGame();
        $timing = $this->resolveCreateTiming($data);

        try {
            $result = DB::transaction(function () use ($user, $data, $config, $game, $timing): array {
                $duplicate = TreasureBox::query()
                    ->where('creator_id', $user->getKey())
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return [
                        'treasure' => $duplicate->loadMissing(['config', 'creator', 'rewards']),
                        'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                        'replay' => true,
                        'scheduled' => false,
                        'countdown' => false,
                    ];
                }

                $paysDiamonds = $data->rewardType === TreasureBox::REWARD_DIAMONDS;
                $wallet = $this->wallets->lockForUser($user);
                $balance = $paysDiamonds ? (int) $wallet->diamonds_balance : (int) $wallet->coins_balance;

                if ($balance < $data->totalAmount) {
                    throw ValidationException::withMessages([
                        $paysDiamonds ? 'diamonds' : 'coins' => 'Insufficient balance.',
                    ]);
                }

                $metadata = $data->metadata;
                $metadata['distribution_type'] = $data->distributionType;
                if (! $this->isWalletRewardType($data->rewardType)) {
                    $metadata['funded_with'] = 'coins';
                    $metadata['non_wallet_reward'] = true;
                }

                $treasure = $this->treasures->createTreasure([
                    'game_id' => $game->getKey(),
                    'config_id' => $config->getKey(),
                    'creator_id' => $user->getKey(),
                    'type' => $data->type,
                    'status' => $timing['status'],
                    'reward_type' => $data->rewardType,
                    'total_amount' => $data->totalAmount,
                    'remaining_amount' => $data->totalAmount,
                    'winner_count' => $data->winnerCount,
                    'participant_count' => 0,
                    'room_id' => $data->roomId,
                    'room_type' => $data->roomType,
                    'visibility' => $data->visibility,
                    'eligibility_scope' => $data->eligibilityScope,
                    'whitelist_user_ids' => $data->eligibilityScope === TreasureBox::SCOPE_WHITELIST
                        ? array_values(array_unique($data->whitelistUserIds ?? []))
                        : null,
                    'min_watch_time_seconds' => $data->minWatchTimeSeconds,
                    'min_user_level' => $data->minUserLevel,
                    'min_gift_value' => $data->minGiftValue,
                    'countdown_seconds' => $data->countdownSeconds,
                    'claim_duration_seconds' => $data->claimDurationSeconds,
                    'scheduled_at' => $timing['scheduled_at'],
                    'countdown_started_at' => $timing['countdown_started_at'],
                    'opens_at' => $timing['opens_at'],
                    'draw_at' => $timing['draw_at'],
                    'expires_at' => $timing['expires_at'],
                    'auto_start' => $data->autoStart,
                    'message' => $data->message,
                    'idempotency_key' => $data->idempotencyKey,
                    'metadata' => $metadata === [] ? null : $metadata,
                ]);

                $this->treasures->createReward([
                    'treasure_box_id' => $treasure->getKey(),
                    'reward_type' => $data->rewardType,
                    'amount' => $data->totalAmount,
                    'label' => ucfirst(str_replace('_', ' ', $data->rewardType)),
                    'sort_order' => 0,
                    'metadata' => $metadata === [] ? null : $metadata,
                ]);

                $play = $this->games->createPlay([
                    'game_id' => $game->getKey(),
                    'user_id' => $user->getKey(),
                    'bet_coins' => $paysDiamonds ? 0 : $data->totalAmount,
                    'bet_diamonds' => $paysDiamonds ? $data->totalAmount : 0,
                    'reward_coins' => 0,
                    'reward_diamonds' => 0,
                    'result' => GamePlay::RESULT_LOST,
                    'status' => GamePlay::STATUS_COMPLETED,
                    'input' => [
                        'action' => 'plugin_create',
                        'idempotency_key' => $data->idempotencyKey,
                        'type' => $data->type,
                        'reward_type' => $data->rewardType,
                        'distribution_type' => $data->distributionType,
                        'visibility' => $data->visibility,
                        'eligibility_scope' => $data->eligibilityScope,
                        'total_amount' => $data->totalAmount,
                        'winner_count' => $data->winnerCount,
                        'countdown_seconds' => $data->countdownSeconds,
                        'claim_duration_seconds' => $data->claimDurationSeconds,
                    ],
                    'outcome' => [
                        'treasure_box_id' => $treasure->id,
                        'animation' => 'treasure_create',
                    ],
                    'metadata' => $data->metadata === [] ? null : $data->metadata,
                    'created_at' => now(),
                ]);

                $wallet->decrement($paysDiamonds ? 'diamonds_balance' : 'coins_balance', $data->totalAmount);

                $transaction = WalletTransaction::query()->create([
                    'user_id' => $user->getKey(),
                    'type' => GamePlay::WALLET_TYPE_GAME_BET,
                    'amount' => -$data->totalAmount,
                    'currency' => $paysDiamonds
                        ? WalletTransaction::CURRENCY_DIAMONDS
                        : WalletTransaction::CURRENCY_COINS,
                    'reference_type' => $treasure->getMorphClass(),
                    'reference_id' => $treasure->getKey(),
                    'metadata' => [
                        'game_slug' => Game::SLUG_TREASURE_BOX,
                        'treasure_box_id' => $treasure->id,
                        'idempotency_key' => $data->idempotencyKey,
                        'action' => 'treasure_box_create',
                        'reward_type' => $data->rewardType,
                    ],
                    'created_at' => now(),
                ]);

                $treasure->forceFill([
                    'game_play_id' => $play->getKey(),
                    'wallet_transaction_id' => $transaction->getKey(),
                ])->save();

                return [
                    'treasure' => $treasure->fresh(['config', 'creator', 'rewards']),
                    'wallet' => $wallet->fresh(),
                    'replay' => false,
                    'scheduled' => $timing['status'] === TreasureBox::STATUS_SCHEDULED,
                    'countdown' => $timing['countdown_started_at'] !== null,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $treasure = $this->treasures->findByIdempotency($user, $data->idempotencyKey);
            if ($treasure === null) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Duplicate create request could not be recovered.',
                ]);
            }

            return [
                'treasure' => $treasure,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        if (! $result['replay']) {
            TreasureCreated::dispatch($result['treasure']);

            if ($result['scheduled']) {
                TreasureScheduled::dispatch($result['treasure']);
            } else {
                TreasureOpened::dispatch($result['treasure']);
                if ($result['countdown']) {
                    TreasureCountdownStarted::dispatch($result['treasure']);
                }
            }

            WalletUpdated::dispatch($result['wallet']);
        }

        return [
            'treasure' => $result['treasure'],
            'wallet' => $result['wallet'],
            'replay' => $result['replay'],
        ];
    }

    /**
     * @return array{treasure: TreasureBox, participant: TreasureBoxParticipant, wallet: Wallet, replay: bool}
     */
    public function join(User $user, JoinTreasureBoxDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::JOIN_RATE_KEY, $user->getKey()), self::JOIN_RATE_MAX);
        $this->assertUuid($data->idempotencyKey);

        $replay = $this->treasures->findParticipantByIdempotency($user, $data->idempotencyKey);
        if ($replay !== null) {
            return [
                'treasure' => $replay->treasureBox ?? $this->requireTreasure($data->treasureBoxId),
                'participant' => $replay,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        $peek = $this->requireTreasure($data->treasureBoxId);
        if ($peek->isExpired() || ($peek->expires_at !== null && $peek->expires_at->isPast())) {
            $this->expire($peek->getKey());

            throw ValidationException::withMessages(['treasure_box_id' => 'Treasure box has expired.']);
        }

        if ($peek->draw_at !== null && $peek->draw_at->isPast()
            && in_array($peek->status, [TreasureBox::STATUS_OPEN, TreasureBox::STATUS_COUNTDOWN], true)) {
            $this->drawWinners($peek->getKey());

            throw ValidationException::withMessages(['treasure_box_id' => 'Treasure box draw already started.']);
        }

        try {
            $result = DB::transaction(function () use ($user, $data): array {
                $duplicate = TreasureBoxParticipant::query()
                    ->where('user_id', $user->getKey())
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return [
                        'treasure' => $this->requireTreasure($data->treasureBoxId),
                        'participant' => $duplicate,
                        'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                        'replay' => true,
                    ];
                }

                $treasure = TreasureBox::query()->lockForUpdate()->find($data->treasureBoxId);
                if ($treasure === null) {
                    throw ValidationException::withMessages(['treasure_box_id' => 'Treasure box not found.']);
                }

                if (! $treasure->allowsJoining()) {
                    throw ValidationException::withMessages(['treasure_box_id' => 'Treasure box is not open for joining.']);
                }

                if ($treasure->ownerId() === $user->getKey()) {
                    throw ValidationException::withMessages(['treasure_box_id' => 'You cannot join your own treasure box.']);
                }

                if ($this->treasures->findParticipantFor($treasure, $user) !== null) {
                    throw ValidationException::withMessages(['treasure_box_id' => 'You already joined this treasure box.']);
                }

                $this->assertEligibilitySatisfied($user, $treasure, $data->metadata);

                $participant = $this->treasures->createParticipant([
                    'treasure_box_id' => $treasure->getKey(),
                    'user_id' => $user->getKey(),
                    'joined_at' => now(),
                    'idempotency_key' => $data->idempotencyKey,
                    'metadata' => $data->metadata === [] ? null : $data->metadata,
                ]);

                $treasure->forceFill([
                    'participant_count' => (int) $treasure->participant_count + 1,
                ])->save();

                return [
                    'treasure' => $treasure->fresh(['config', 'creator', 'rewards']),
                    'participant' => $participant->fresh(),
                    'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                    'replay' => false,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $participant = $this->treasures->findParticipantByIdempotency($user, $data->idempotencyKey)
                ?? $this->treasures->findParticipantFor($this->requireTreasure($data->treasureBoxId), $user);

            if ($participant === null) {
                throw ValidationException::withMessages([
                    'treasure_box_id' => 'You already joined this treasure box.',
                ]);
            }

            return [
                'treasure' => $this->requireTreasure($data->treasureBoxId),
                'participant' => $participant,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        if (! $result['replay']) {
            TreasureJoined::dispatch($result['treasure'], $result['participant']);
        }

        return $result;
    }

    public function history(User $user, string $type = 'all', int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->treasures->history($user, $type, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', string $type = 'winners', int $limit = 50): Collection
    {
        return $this->treasures->leaderboard($period, $type, $limit);
    }

    /** @return array<string, mixed> */
    public function statistics(User $user): array
    {
        $this->ensureEligible($user);
        $stats = $this->treasures->statisticsFor($user);

        return [
            'user_id' => $stats->user_id,
            'scope' => $stats->scope,
            'treasures_created' => (int) $stats->treasures_created,
            'treasures_joined' => (int) $stats->treasures_joined,
            'treasures_won' => (int) $stats->treasures_won,
            'total_reward_amount' => (int) $stats->total_reward_amount,
            'total_spent_amount' => (int) $stats->total_spent_amount,
            'jackpots_won' => (int) $stats->jackpots_won,
            'average_join_seconds' => (int) $stats->average_join_seconds,
            'metadata' => $stats->metadata,
        ];
    }

    public function startCountdown(string $treasureId): ?TreasureBox
    {
        $outcome = DB::transaction(function () use ($treasureId): ?array {
            $treasure = TreasureBox::query()->lockForUpdate()->find($treasureId);
            if ($treasure === null) {
                return null;
            }

            if (! in_array($treasure->status, [TreasureBox::STATUS_SCHEDULED, TreasureBox::STATUS_OPEN], true)) {
                return null;
            }

            if ($treasure->countdown_started_at !== null && $treasure->status === TreasureBox::STATUS_COUNTDOWN) {
                return null;
            }

            $startedAt = now();
            $drawAt = $startedAt->copy()->addSeconds((int) $treasure->countdown_seconds);
            $opensAt = $treasure->opens_at ?? $startedAt;
            $expiresAt = $opensAt->copy()->addSeconds((int) $treasure->claim_duration_seconds)
                ->max($drawAt->copy()->addSeconds(60));

            $treasure->forceFill([
                'status' => TreasureBox::STATUS_COUNTDOWN,
                'countdown_started_at' => $startedAt,
                'opens_at' => $opensAt,
                'draw_at' => $drawAt,
                'expires_at' => $treasure->expires_at ?? $expiresAt,
            ])->save();

            return ['treasure' => $treasure->fresh(['config', 'creator', 'rewards'])];
        });

        if ($outcome === null) {
            return null;
        }

        TreasureCountdownStarted::dispatch($outcome['treasure']);
        TreasureOpened::dispatch($outcome['treasure']);

        return $outcome['treasure'];
    }

    public function drawWinners(string $treasureId): ?TreasureBox
    {
        $game = $this->requireGame();

        $outcome = DB::transaction(function () use ($treasureId, $game): ?array {
            $treasure = TreasureBox::query()->lockForUpdate()->find($treasureId);
            if ($treasure === null) {
                return null;
            }

            if (in_array($treasure->status, [TreasureBox::STATUS_FINISHED, TreasureBox::STATUS_EXPIRED, TreasureBox::STATUS_OPENED], true)) {
                return ['treasure' => $treasure->fresh(['config', 'creator', 'rewards', 'winners']), 'winners' => [], 'wallets' => [], 'already' => true];
            }

            if (! in_array($treasure->status, [TreasureBox::STATUS_OPEN, TreasureBox::STATUS_COUNTDOWN], true)) {
                return null;
            }

            if ($treasure->draw_at !== null && $treasure->draw_at->isFuture()) {
                return null;
            }

            $participants = $this->treasures->participantsFor($treasure)->values();
            $pickCount = min((int) $treasure->winner_count, $participants->count());

            $treasure->forceFill(['status' => TreasureBox::STATUS_OPENED])->save();

            if ($pickCount < 1) {
                $treasure->forceFill([
                    'status' => TreasureBox::STATUS_FINISHED,
                    'remaining_amount' => 0,
                ])->save();

                return [
                    'treasure' => $treasure->fresh(['config', 'creator', 'rewards', 'winners']),
                    'winners' => [],
                    'wallets' => [],
                    'already' => false,
                    'empty' => true,
                ];
            }

            $indexes = $pickCount === $participants->count()
                ? range(0, $pickCount - 1)
                : $this->rng->sampleUnique(0, $participants->count() - 1, $pickCount);

            $selected = collect($indexes)->map(fn (int $i) => $participants[$i])->values();
            $remainingAmount = (int) $treasure->total_amount;
            $remainingCount = $selected->count();
            $winners = [];
            $wallets = [];

            foreach ($selected as $index => $participant) {
                $share = $this->resolveShareFor($treasure, $remainingAmount, $remainingCount);
                $remainingAmount -= $share;
                $remainingCount -= 1;

                $isJackpot = $treasure->rewardType() === TreasureBox::REWARD_JACKPOT
                    || ($index === 0 && $treasure->rewardType() === TreasureBox::REWARD_MYSTERY && $share === max($share, 1));

                if ($treasure->rewardType() === TreasureBox::REWARD_JACKPOT && $index === 0) {
                    $isJackpot = true;
                }

                $winnerUser = User::query()->find($participant->user_id);
                if ($winnerUser === null) {
                    continue;
                }

                $credit = $this->creditWinner($treasure, $winnerUser, $participant, $share, $isJackpot, $game);
                $winners[] = $credit['winner'];
                if ($credit['wallet'] !== null) {
                    $wallets[] = $credit['wallet'];
                }
            }

            $treasure->forceFill([
                'remaining_amount' => 0,
                'status' => TreasureBox::STATUS_FINISHED,
            ])->save();

            return [
                'treasure' => $treasure->fresh(['config', 'creator', 'rewards', 'winners']),
                'winners' => $winners,
                'wallets' => $wallets,
                'already' => false,
                'empty' => false,
            ];
        });

        if ($outcome === null || ($outcome['already'] ?? false)) {
            return $outcome['treasure'] ?? null;
        }

        TreasureOpened::dispatch($outcome['treasure']);

        foreach ($outcome['winners'] as $winner) {
            TreasureWinnerSelected::dispatch($outcome['treasure'], $winner);
            TreasureRewarded::dispatch($outcome['treasure'], $winner);
        }

        TreasureFinished::dispatch($outcome['treasure']);

        foreach ($outcome['wallets'] as $wallet) {
            WalletUpdated::dispatch($wallet);
        }

        return $outcome['treasure'];
    }

    public function expire(string $treasureId): ?TreasureBox
    {
        $outcome = DB::transaction(function () use ($treasureId): ?array {
            $treasure = TreasureBox::query()->lockForUpdate()->find($treasureId);
            if ($treasure === null) {
                return null;
            }

            if (in_array($treasure->status, [TreasureBox::STATUS_FINISHED, TreasureBox::STATUS_EXPIRED, TreasureBox::STATUS_OPENED], true)) {
                return null;
            }

            $refunded = $this->expireAndRefund($treasure);

            return ['treasure' => $treasure->fresh(['config', 'creator', 'rewards']), 'refunded' => $refunded];
        });

        if ($outcome === null) {
            return null;
        }

        TreasureExpired::dispatch($outcome['treasure'], $outcome['refunded']);

        if ($outcome['refunded'] > 0) {
            $creator = $outcome['treasure']->creator;
            if ($creator !== null) {
                WalletUpdated::dispatch($this->wallets->findOrCreateForUser($creator)->fresh());
            }
        }

        return $outcome['treasure'];
    }

    /**
     * @return array{started: int, drawn: int, expired: int, auto_created: int}
     */
    public function processDue(int $limit = 100): array
    {
        $started = 0;
        $drawn = 0;
        $expired = 0;
        $autoCreated = $this->processAutoRules($limit);

        foreach ($this->treasures->dueForScheduleStart($limit) as $treasure) {
            if ($this->startCountdown($treasure->getKey()) !== null) {
                $started++;
            }
        }

        foreach ($this->treasures->dueForDraw($limit) as $treasure) {
            if ($this->drawWinners($treasure->getKey()) !== null) {
                $drawn++;
            }
        }

        foreach ($this->treasures->dueForExpiry($limit) as $treasure) {
            if ($this->expire($treasure->getKey()) !== null) {
                $expired++;
            }
        }

        return [
            'started' => $started,
            'drawn' => $drawn,
            'expired' => $expired,
            'auto_created' => $autoCreated,
        ];
    }

    /**
     * Creates automatic treasures from admin-configured rules.
     * Each rule must include a funded `creator_user_id` sponsor account.
     */
    public function processAutoRules(int $limit = 20): int
    {
        $config = $this->treasures->activeConfig();
        if ($config === null || ! $config->isActive()) {
            return 0;
        }

        $rules = $config->auto_rules;
        if (! is_array($rules) || $rules === []) {
            return 0;
        }

        // Support both a list of rule objects and a map keyed by rule id.
        $normalized = array_is_list($rules) ? $rules : array_values(array_filter(
            $rules,
            static fn (mixed $rule): bool => is_array($rule),
        ));

        $created = 0;

        foreach ($normalized as $rule) {
            if ($created >= $limit) {
                break;
            }

            if (! is_array($rule) || ! ($rule['enabled'] ?? false)) {
                continue;
            }

            $ruleId = (string) ($rule['id'] ?? '');
            $creatorId = (string) ($rule['creator_user_id'] ?? '');
            $intervalMinutes = max(1, (int) ($rule['interval_minutes'] ?? 60));

            if ($ruleId === '' || $creatorId === '' || ! Str::isUuid($creatorId)) {
                continue;
            }

            $already = TreasureBox::query()
                ->where('type', TreasureBox::TYPE_AUTO)
                ->where('creator_id', $creatorId)
                ->where('metadata->auto_rule_id', $ruleId)
                ->where('created_at', '>=', now()->subMinutes($intervalMinutes))
                ->exists();

            if ($already) {
                continue;
            }

            $creator = User::query()->find($creatorId);
            if ($creator === null) {
                continue;
            }

            $bucket = (int) floor(now()->timestamp / ($intervalMinutes * 60));
            $idempotencyKey = (string) Str::uuid()->toString();
            // Deterministic-enough replay protection within the interval bucket.
            $existingBucket = TreasureBox::query()
                ->where('creator_id', $creatorId)
                ->where('metadata->auto_rule_id', $ruleId)
                ->where('metadata->auto_rule_bucket', $bucket)
                ->first();

            if ($existingBucket !== null) {
                continue;
            }

            try {
                $result = $this->create($creator, new CreateTreasureBoxDTO(
                    idempotencyKey: $idempotencyKey,
                    type: TreasureBox::TYPE_AUTO,
                    rewardType: (string) ($rule['reward_type'] ?? TreasureBox::REWARD_COINS),
                    distributionType: (string) ($rule['distribution_type'] ?? TreasureBox::DIST_RANDOM),
                    visibility: (string) ($rule['visibility'] ?? TreasureBox::VIS_PUBLIC),
                    eligibilityScope: (string) ($rule['eligibility_scope'] ?? TreasureBox::SCOPE_EVERYONE),
                    totalAmount: max(1, (int) ($rule['total_amount'] ?? $config->min_coins_amount)),
                    winnerCount: max(1, (int) ($rule['winner_count'] ?? 1)),
                    countdownSeconds: max(1, (int) ($rule['countdown_seconds'] ?? $config->default_countdown_seconds)),
                    claimDurationSeconds: max(1, (int) ($rule['claim_duration_seconds'] ?? $config->default_claim_duration_seconds)),
                    autoStart: (bool) ($rule['auto_start'] ?? true),
                    scheduledAt: null,
                    roomType: $rule['room_type'] ?? null,
                    roomId: $rule['room_id'] ?? null,
                    whitelistUserIds: isset($rule['whitelist_user_ids']) && is_array($rule['whitelist_user_ids'])
                        ? array_map('strval', $rule['whitelist_user_ids'])
                        : null,
                    minWatchTimeSeconds: isset($rule['min_watch_time_seconds']) ? (int) $rule['min_watch_time_seconds'] : null,
                    minUserLevel: isset($rule['min_user_level']) ? (int) $rule['min_user_level'] : null,
                    minGiftValue: isset($rule['min_gift_value']) ? (int) $rule['min_gift_value'] : null,
                    message: isset($rule['message']) ? (string) $rule['message'] : 'Auto Treasure',
                    metadata: [
                        'source' => 'auto_rule',
                        'auto_rule_id' => $ruleId,
                        'auto_rule_bucket' => $bucket,
                        'interval_minutes' => $intervalMinutes,
                    ],
                ));

                if (! $result['replay']) {
                    $created++;
                }
            } catch (\Throwable) {
                // Skip broken rules without aborting the rest of the scheduler tick.
                continue;
            }
        }

        return $created;
    }

    /**
     * @return array{winner: TreasureBoxWinner, wallet: ?Wallet}
     */
    private function creditWinner(
        TreasureBox $treasure,
        User $user,
        TreasureBoxParticipant $participant,
        int $share,
        bool $isJackpot,
        Game $game,
    ): array {
        $existing = TreasureBoxWinner::query()
            ->where('treasure_box_id', $treasure->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        if ($existing !== null) {
            return ['winner' => $existing, 'wallet' => null];
        }

        $idempotencyKey = (string) Str::uuid();
        $wallet = null;
        $transactionId = null;
        $rewardCoins = 0;
        $rewardDiamonds = 0;
        $isWallet = $treasure->isWalletReward();

        if ($isWallet) {
            $wallet = $this->wallets->lockForUser($user);
            if ($treasure->paysCoins()) {
                $wallet->increment('coins_balance', $share);
                $rewardCoins = $share;
            } else {
                $wallet->increment('diamonds_balance', $share);
                $rewardDiamonds = $share;
            }

            $transaction = WalletTransaction::query()->create([
                'user_id' => $user->getKey(),
                'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                'amount' => $share,
                'currency' => $treasure->paysDiamonds()
                    ? WalletTransaction::CURRENCY_DIAMONDS
                    : WalletTransaction::CURRENCY_COINS,
                'reference_type' => $treasure->getMorphClass(),
                'reference_id' => $treasure->getKey(),
                'metadata' => [
                    'game_slug' => Game::SLUG_TREASURE_BOX,
                    'treasure_box_id' => $treasure->id,
                    'participant_id' => $participant->id,
                    'action' => 'treasure_box_reward',
                    'is_jackpot' => $isJackpot,
                ],
                'created_at' => now(),
            ]);
            $transactionId = $transaction->getKey();
        }

        $play = $this->games->createPlay([
            'game_id' => $game->getKey(),
            'user_id' => $user->getKey(),
            'bet_coins' => 0,
            'bet_diamonds' => 0,
            'reward_coins' => $rewardCoins,
            'reward_diamonds' => $rewardDiamonds,
            'result' => GamePlay::RESULT_CLAIMED,
            'status' => GamePlay::STATUS_COMPLETED,
            'input' => [
                'action' => 'plugin_win',
                'treasure_box_id' => $treasure->id,
                'participant_id' => $participant->id,
            ],
            'outcome' => [
                'treasure_box_id' => $treasure->id,
                'reward_type' => $treasure->rewardType(),
                'reward_amount' => $share,
                'is_jackpot' => $isJackpot,
                'animation' => 'treasure_win',
            ],
            'metadata' => [
                'requires_fulfillment' => ! $isWallet,
            ],
            'created_at' => now(),
        ]);

        GameReward::query()->create([
            'user_id' => $user->getKey(),
            'game_id' => $game->getKey(),
            'play_id' => $play->getKey(),
            'coins' => $rewardCoins,
            'diamonds' => $rewardDiamonds,
            'status' => $isWallet ? GameReward::STATUS_GRANTED : GameReward::STATUS_PENDING_FULFILLMENT,
            'metadata' => [
                'treasure_box_id' => $treasure->id,
                'participant_id' => $participant->id,
                'reward_type' => $treasure->rewardType(),
                'reward_amount' => $share,
                'is_jackpot' => $isJackpot,
                'requires_fulfillment' => ! $isWallet,
            ],
        ]);

        $winner = $this->treasures->createWinner([
            'treasure_box_id' => $treasure->getKey(),
            'user_id' => $user->getKey(),
            'participant_id' => $participant->getKey(),
            'reward_type' => $treasure->rewardType(),
            'reward_amount' => $share,
            'is_jackpot' => $isJackpot,
            'wallet_transaction_id' => $transactionId,
            'play_id' => $play->getKey(),
            'rewarded_at' => now(),
            'idempotency_key' => $idempotencyKey,
            'metadata' => [
                'requires_fulfillment' => ! $isWallet,
            ],
        ]);

        return [
            'winner' => $winner->fresh(),
            'wallet' => $wallet?->fresh(),
        ];
    }

    private function expireAndRefund(TreasureBox $treasure): int
    {
        if ($treasure->status === TreasureBox::STATUS_EXPIRED) {
            return 0;
        }

        $remaining = $treasure->amountLeft();
        $creator = $treasure->creator;
        $refundEnabled = $treasure->config?->refund_unclaimed ?? true;
        $refunded = 0;

        if ($remaining > 0 && $creator !== null && $refundEnabled) {
            $paysDiamonds = $treasure->paysDiamonds();
            // Non-wallet rewards were funded with coins.
            $wallet = $this->wallets->lockForUser($creator);
            $wallet->increment($paysDiamonds ? 'diamonds_balance' : 'coins_balance', $remaining);
            $refunded = $remaining;

            WalletTransaction::query()->create([
                'user_id' => $creator->getKey(),
                'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                'amount' => $remaining,
                'currency' => $paysDiamonds
                    ? WalletTransaction::CURRENCY_DIAMONDS
                    : WalletTransaction::CURRENCY_COINS,
                'reference_type' => $treasure->getMorphClass(),
                'reference_id' => $treasure->getKey(),
                'metadata' => [
                    'game_slug' => Game::SLUG_TREASURE_BOX,
                    'treasure_box_id' => $treasure->id,
                    'action' => 'treasure_box_expiry_refund',
                ],
                'created_at' => now(),
            ]);
        }

        $treasure->forceFill([
            'remaining_amount' => 0,
            'status' => TreasureBox::STATUS_EXPIRED,
        ])->save();

        return $refunded;
    }

    private function resolveShareFor(TreasureBox $treasure, int $remainingAmount, int $remainingCount): int
    {
        if ($remainingAmount < 1 || $remainingCount < 1) {
            return 0;
        }

        $share = $treasure->distributionType() === TreasureBox::DIST_FIXED
            ? $this->fixedShare($remainingAmount, $remainingCount)
            : $this->randomShare($remainingAmount, $remainingCount);

        return max(1, min($share, $remainingAmount - ($remainingCount - 1)));
    }

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

    private function fixedShare(int $remainingAmount, int $remainingCount): int
    {
        if ($remainingCount <= 1) {
            return max(1, $remainingAmount);
        }

        return max(1, intdiv($remainingAmount, $remainingCount));
    }

    /** @param  array<string, mixed>  $metadata */
    private function assertEligibilitySatisfied(User $joiner, TreasureBox $treasure, array $metadata = []): void
    {
        $scope = $treasure->eligibility_scope ?? TreasureBox::SCOPE_EVERYONE;
        $creator = $treasure->creator;

        $allowed = match ($scope) {
            TreasureBox::SCOPE_EVERYONE => true,
            TreasureBox::SCOPE_FOLLOWERS => $creator !== null && $this->follows->isFollowing($joiner, $creator),
            TreasureBox::SCOPE_VIP => $this->vips->findForUser($joiner)?->isActive() ?? false,
            TreasureBox::SCOPE_SVIP => $this->svips->findForUser($joiner)?->isActive() ?? false,
            TreasureBox::SCOPE_FAMILY => $this->sharesGroup(
                $this->families->findApprovedMembershipForUser($joiner)?->family_id,
                $creator === null ? null : $this->families->findApprovedMembershipForUser($creator)?->family_id,
            ),
            TreasureBox::SCOPE_AGENCY => $this->sharesGroup(
                $this->agencies->findApprovedMembershipForUser($joiner)?->agency_id,
                $creator === null ? null : $this->agencies->findApprovedMembershipForUser($creator)?->agency_id,
            ),
            TreasureBox::SCOPE_MIN_WATCH_TIME => (int) ($metadata['watch_time_seconds']
                ?? $joiner->getAttribute('watch_time_seconds')
                ?? 0) >= (int) ($treasure->min_watch_time_seconds ?? 0),
            TreasureBox::SCOPE_MIN_USER_LEVEL => (int) ($metadata['user_level']
                ?? $joiner->getAttribute('level')
                ?? $joiner->profile?->level
                ?? 0) >= (int) ($treasure->min_user_level ?? 0),
            TreasureBox::SCOPE_MIN_GIFT_VALUE => (int) ($metadata['gift_value']
                ?? $joiner->getAttribute('gift_value')
                ?? 0) >= (int) ($treasure->min_gift_value ?? 0),
            TreasureBox::SCOPE_WHITELIST => in_array(
                (string) $joiner->getKey(),
                array_map('strval', $treasure->whitelist_user_ids ?? []),
                true,
            ),
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages([
                'treasure_box_id' => 'You are not eligible to join this treasure box.',
                'eligibility_scope' => $scope,
            ]);
        }
    }

    private function sharesGroup(?string $a, ?string $b): bool
    {
        return $a !== null && $a === $b;
    }

    /**
     * @return array{
     *     status: string,
     *     scheduled_at: ?Carbon,
     *     countdown_started_at: ?Carbon,
     *     opens_at: ?Carbon,
     *     draw_at: ?Carbon,
     *     expires_at: ?Carbon
     * }
     */
    private function resolveCreateTiming(CreateTreasureBoxDTO $data): array
    {
        $isScheduled = $data->type === TreasureBox::TYPE_SCHEDULED
            || ($data->scheduledAt !== null && ! $data->autoStart);

        if ($isScheduled) {
            $scheduledAt = $data->scheduledAt !== null
                ? Carbon::parse($data->scheduledAt)
                : now()->addMinutes(5);

            return [
                'status' => TreasureBox::STATUS_SCHEDULED,
                'scheduled_at' => $scheduledAt,
                'countdown_started_at' => null,
                'opens_at' => $scheduledAt,
                'draw_at' => $scheduledAt->copy()->addSeconds($data->countdownSeconds),
                'expires_at' => $scheduledAt->copy()
                    ->addSeconds($data->claimDurationSeconds)
                    ->max($scheduledAt->copy()->addSeconds($data->countdownSeconds + 60)),
            ];
        }

        $startNow = $data->autoStart
            || in_array($data->type, [TreasureBox::TYPE_INSTANT, TreasureBox::TYPE_AUTO, TreasureBox::TYPE_LIVE_ROOM, TreasureBox::TYPE_VOICE_ROOM], true);

        $opensAt = now();
        $countdownStarted = $startNow ? $opensAt->copy() : null;
        $drawAt = $countdownStarted?->copy()->addSeconds($data->countdownSeconds);
        $expiresAt = $opensAt->copy()->addSeconds($data->claimDurationSeconds);
        if ($drawAt !== null) {
            $expiresAt = $expiresAt->max($drawAt->copy()->addSeconds(60));
        }

        return [
            'status' => $startNow ? TreasureBox::STATUS_COUNTDOWN : TreasureBox::STATUS_OPEN,
            'scheduled_at' => null,
            'countdown_started_at' => $countdownStarted,
            'opens_at' => $opensAt,
            'draw_at' => $drawAt,
            'expires_at' => $expiresAt,
        ];
    }

    private function validateCreateInput(TreasureBoxConfig $config, CreateTreasureBoxDTO $data): void
    {
        if (! in_array($data->type, TreasureBox::TYPES, true) || ! $config->typeAllowed($data->type)) {
            throw ValidationException::withMessages(['type' => 'This treasure type is not available.']);
        }

        if (! in_array($data->rewardType, TreasureBox::REWARD_TYPES, true) || ! $config->rewardTypeAllowed($data->rewardType)) {
            throw ValidationException::withMessages(['reward_type' => 'This reward type is not available.']);
        }

        if (! in_array($data->distributionType, TreasureBox::DISTRIBUTION_TYPES, true)) {
            throw ValidationException::withMessages(['distribution_type' => 'This distribution type is not available.']);
        }

        if (! in_array($data->visibility, TreasureBox::VISIBILITIES, true) || ! $config->visibilityAllowed($data->visibility)) {
            throw ValidationException::withMessages(['visibility' => 'This visibility is not available.']);
        }

        if (! in_array($data->eligibilityScope, TreasureBox::ELIGIBILITY_SCOPES, true)
            || ! $config->eligibilityAllowed($data->eligibilityScope)) {
            throw ValidationException::withMessages(['eligibility_scope' => 'This eligibility scope is not available.']);
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

        if ($data->countdownSeconds < (int) $config->min_countdown_seconds
            || $data->countdownSeconds > (int) $config->max_countdown_seconds) {
            throw ValidationException::withMessages([
                'countdown_seconds' => "Countdown must be between {$config->min_countdown_seconds} and {$config->max_countdown_seconds} seconds.",
            ]);
        }

        if ($data->claimDurationSeconds < (int) $config->min_claim_duration_seconds
            || $data->claimDurationSeconds > (int) $config->max_claim_duration_seconds) {
            throw ValidationException::withMessages([
                'claim_duration_seconds' => "Join duration must be between {$config->min_claim_duration_seconds} and {$config->max_claim_duration_seconds} seconds.",
            ]);
        }

        if ($data->roomType !== null && ! $config->roomTypeAllowed($data->roomType)) {
            throw ValidationException::withMessages(['room_type' => 'This room type is not available.']);
        }

        $needsRoom = $data->roomType !== null
            || in_array($data->visibility, [TreasureBox::VIS_LIVE_ROOM, TreasureBox::VIS_VOICE_ROOM], true)
            || in_array($data->type, [TreasureBox::TYPE_LIVE_ROOM, TreasureBox::TYPE_VOICE_ROOM], true);

        if ($needsRoom && ($data->roomId === null || $data->roomId === '')) {
            throw ValidationException::withMessages(['room_id' => 'Room treasure boxes require a room id.']);
        }

        if ($data->roomId !== null && $data->roomType === null) {
            throw ValidationException::withMessages(['room_type' => 'Room treasure boxes require a room type.']);
        }

        if ($data->eligibilityScope === TreasureBox::SCOPE_WHITELIST && ($data->whitelistUserIds ?? []) === []) {
            throw ValidationException::withMessages([
                'whitelist_user_ids' => 'Whitelist treasures require at least one user id.',
            ]);
        }

        if ($data->type === TreasureBox::TYPE_SCHEDULED && $data->scheduledAt === null) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Scheduled treasures require a scheduled_at time.',
            ]);
        }

        if ($data->eligibilityScope === TreasureBox::SCOPE_MIN_WATCH_TIME && ($data->minWatchTimeSeconds ?? 0) < 1) {
            throw ValidationException::withMessages([
                'min_watch_time_seconds' => 'min_watch_time eligibility requires min_watch_time_seconds.',
            ]);
        }

        if ($data->eligibilityScope === TreasureBox::SCOPE_MIN_USER_LEVEL && ($data->minUserLevel ?? 0) < 1) {
            throw ValidationException::withMessages([
                'min_user_level' => 'min_user_level eligibility requires min_user_level.',
            ]);
        }

        if ($data->eligibilityScope === TreasureBox::SCOPE_MIN_GIFT_VALUE && ($data->minGiftValue ?? 0) < 1) {
            throw ValidationException::withMessages([
                'min_gift_value' => 'min_gift_value eligibility requires min_gift_value.',
            ]);
        }
    }

    private function assertCreateAllowed(User $user, TreasureBoxConfig $config): void
    {
        if ($config->max_created_per_day === null) {
            return;
        }

        if ($this->treasures->countCreatedToday($user, $config->getKey()) >= (int) $config->max_created_per_day) {
            throw ValidationException::withMessages(['create' => 'Daily treasure box limit reached.']);
        }
    }

    private function isWalletRewardType(string $rewardType): bool
    {
        return in_array($rewardType, TreasureBox::WALLET_REWARD_TYPES, true);
    }

    private function requireActiveConfig(): TreasureBoxConfig
    {
        $config = $this->treasures->activeConfig();
        if ($config === null || ! $config->isActive()) {
            throw ValidationException::withMessages(['config' => 'Treasure Box is not configured.']);
        }

        return $config;
    }

    private function requireGame(): Game
    {
        $game = Game::query()->where('slug', Game::SLUG_TREASURE_BOX)->first();
        if ($game === null || ! $game->isActive()) {
            throw ValidationException::withMessages(['game' => 'Treasure Box game is unavailable.']);
        }

        return $game;
    }

    private function requireTreasure(string $treasureBoxId): TreasureBox
    {
        $treasure = $this->treasures->findTreasure($treasureBoxId);
        if ($treasure === null) {
            throw ValidationException::withMessages(['treasure_box_id' => 'Treasure box not found.']);
        }

        return $treasure;
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
            throw new AuthorizationException('Only active registered users can use Treasure Boxes.');
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
