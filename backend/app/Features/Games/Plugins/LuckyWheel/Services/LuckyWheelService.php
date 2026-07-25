<?php

namespace App\Features\Games\Plugins\LuckyWheel\Services;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Plugins\LuckyWheel\DTOs\LuckyWheelDTO;
use App\Features\Games\Plugins\LuckyWheel\Events\JackpotWon;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelFinished;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelJackpotWon;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelPlayed;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelRewarded;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelRewardWon;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelStarted;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelConfig;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSector;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use App\Features\Games\Plugins\LuckyWheel\Repositories\Contracts\LuckyWheelRepository;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
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

final readonly class LuckyWheelService
{
    private const SPIN_RATE_KEY = 'lucky-wheel-spin:%s';

    private const RATE_MAX = 30;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private LuckyWheelRepository $luckyWheel,
        private WalletRepository $wallets,
        private GameRepository $games,
        private GameRng $rng,
    ) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        $this->ensureEligible($user);

        $config = $this->requireActiveConfig();
        $spinsToday = $this->luckyWheel->countSpinsToday($user, $config->getKey());
        $lastSpinAt = $this->luckyWheel->lastSpinAt($user, $config->getKey());
        $cooldownRemaining = 0;
        if ($config->cooldown_seconds > 0 && $lastSpinAt !== null) {
            $elapsed = $lastSpinAt->diffInSeconds(now());
            $cooldownRemaining = max(0, $config->cooldown_seconds - $elapsed);
        }

        return [
            'id' => $config->id,
            'slug' => $config->slug,
            'name' => $config->name,
            'description' => $config->description,
            'version' => $config->version,
            'spin_cost_coins' => $config->spin_cost_coins,
            'daily_free_spins' => $config->daily_free_spins,
            'max_spins_per_day' => $config->max_spins_per_day,
            'cooldown_seconds' => $config->cooldown_seconds,
            'cooldown_remaining_seconds' => $cooldownRemaining,
            'jackpot_enabled' => $config->jackpot_enabled,
            'jackpot_coins' => $config->jackpot_coins,
            'status' => $config->status,
            'spins_today' => $spinsToday,
            'remaining_spins_today' => $config->max_spins_per_day === null
                ? null
                : max(0, $config->max_spins_per_day - $spinsToday),
            'sectors' => $config->sectors->map(fn (LuckyWheelSector $sector): array => [
                'id' => $sector->id,
                'position' => $sector->position,
                'label' => $sector->label,
                'reward_type' => $sector->reward_type,
                'reward_amount' => $sector->reward_amount,
                'reward_reference' => $sector->reward_reference,
                'color' => $sector->color,
                'icon' => $sector->icon,
                'is_jackpot' => $sector->is_jackpot,
                'requires_fulfillment' => $sector->requires_fulfillment,
                'metadata' => $sector->metadata,
            ])->values()->all(),
            'metadata' => $config->metadata,
        ];
    }

    /**
     * @return array{spin: LuckyWheelSpin, wallet: Wallet, replay: bool}
     */
    public function spin(User $user, LuckyWheelDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::SPIN_RATE_KEY, $user->getKey()));

        if (! Str::isUuid($data->idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency key must be a valid UUID.',
            ]);
        }

        $existing = $this->luckyWheel->findSpinByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            return [
                'spin' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        $config = $this->requireActiveConfig();
        $this->assertSpinAllowed($user, $config);

        $spinsToday = $this->luckyWheel->countSpinsToday($user, $config->getKey());
        $freeRemaining = max(0, (int) $config->daily_free_spins - $spinsToday);
        $cost = $freeRemaining > 0 ? 0 : (int) $config->spin_cost_coins;

        if ($data->costCoins !== null && $data->costCoins !== $cost) {
            throw ValidationException::withMessages([
                'cost_coins' => "Spin cost must be {$cost} coins.",
            ]);
        }

        $game = Game::query()->where('slug', Game::SLUG_LUCKY_WHEEL)->first();
        if ($game === null || ! $game->isActive()) {
            throw ValidationException::withMessages(['game' => 'Lucky Wheel game is unavailable.']);
        }

        $sectors = $config->sectors->filter(fn (LuckyWheelSector $s) => $s->isActive() && $s->weight > 0)->values();
        if ($sectors->isEmpty()) {
            throw ValidationException::withMessages(['config' => 'Lucky Wheel has no active sectors.']);
        }

        $totalWeight = (int) $sectors->sum('weight');
        if ($totalWeight < 1) {
            throw ValidationException::withMessages(['config' => 'Lucky Wheel is misconfigured.']);
        }

        try {
            $result = DB::transaction(function () use ($user, $data, $config, $cost, $game, $sectors, $totalWeight): array {
                // Re-check idempotency under lock-friendly path.
                $existing = LuckyWheelSpin::query()
                    ->where('user_id', $user->getKey())
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return [
                        'spin' => $existing->loadMissing(['sector', 'config', 'play']),
                        'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                        'replay' => true,
                    ];
                }

                $wallet = $this->wallets->lockForUser($user);
                if ($cost > 0 && $wallet->coins_balance < $cost) {
                    throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
                }

                $roll = $this->rng->int(1, $totalWeight);
                $cursor = 0;
                $selected = $sectors->first();
                foreach ($sectors as $sector) {
                    $cursor += (int) $sector->weight;
                    if ($roll <= $cursor) {
                        $selected = $sector;
                        break;
                    }
                }

                /** @var LuckyWheelSector $selected */
                $rewardType = $selected->reward_type;
                $rewardAmount = (int) $selected->reward_amount;
                $isJackpot = (bool) $selected->is_jackpot;
                if ($isJackpot && $config->jackpot_enabled && $config->jackpot_coins > 0 && $rewardType === LuckyWheelSector::REWARD_TYPE_COINS) {
                    $rewardAmount = max($rewardAmount, (int) $config->jackpot_coins);
                }

                $serverSeed = bin2hex(random_bytes(16));
                $pendingSpin = $this->luckyWheel->createSpin([
                    'user_id' => $user->getKey(),
                    'config_id' => $config->getKey(),
                    'sector_id' => $selected->getKey(),
                    'game_id' => $game->getKey(),
                    'idempotency_key' => $data->idempotencyKey,
                    'status' => LuckyWheelSpin::STATUS_PENDING,
                    'cost_coins' => $cost,
                    'reward_type' => $rewardType,
                    'reward_amount' => $rewardAmount,
                    'reward_currency' => $this->currencyFor($rewardType),
                    'reward_reference' => $selected->reward_reference,
                    'is_jackpot' => $isJackpot,
                    'sector_position' => $selected->position,
                    'sector_label' => $selected->label,
                    'config_version' => $config->version,
                    'server_seed' => $serverSeed,
                    'roll' => $roll,
                    'total_weight' => $totalWeight,
                    'outcome' => null,
                    'metadata' => array_filter([
                        'client_session_id' => $data->clientSessionId,
                        'request_metadata' => $data->metadata === [] ? null : $data->metadata,
                    ]),
                ]);

                LuckyWheelStarted::dispatch($pendingSpin);

                $betTxId = null;
                if ($cost > 0) {
                    $wallet->decrement('coins_balance', $cost);
                    $betTx = WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_BET,
                        'amount' => -$cost,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $pendingSpin->getMorphClass(),
                        'reference_id' => $pendingSpin->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_LUCKY_WHEEL,
                            'spin_id' => $pendingSpin->id,
                            'idempotency_key' => $data->idempotencyKey,
                            'action' => 'lucky_wheel_spin_cost',
                        ],
                        'created_at' => now(),
                    ]);
                    $betTxId = $betTx->getKey();
                }

                $rewardCoins = 0;
                $rewardDiamonds = 0;
                $needsFulfillment = $selected->requires_fulfillment
                    || ! $selected->isWalletReward()
                    || $rewardType === LuckyWheelSector::REWARD_TYPE_NONE;

                if ($rewardType === LuckyWheelSector::REWARD_TYPE_COINS && $rewardAmount > 0) {
                    $wallet->increment('coins_balance', $rewardAmount);
                    $rewardCoins = $rewardAmount;
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                        'amount' => $rewardAmount,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $pendingSpin->getMorphClass(),
                        'reference_id' => $pendingSpin->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_LUCKY_WHEEL,
                            'spin_id' => $pendingSpin->id,
                            'idempotency_key' => $data->idempotencyKey,
                            'action' => 'lucky_wheel_reward',
                            'is_jackpot' => $isJackpot,
                        ],
                        'created_at' => now(),
                    ]);
                    $needsFulfillment = false;
                } elseif ($rewardType === LuckyWheelSector::REWARD_TYPE_DIAMONDS && $rewardAmount > 0) {
                    $wallet->increment('diamonds_balance', $rewardAmount);
                    $rewardDiamonds = $rewardAmount;
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                        'amount' => $rewardAmount,
                        'currency' => WalletTransaction::CURRENCY_DIAMONDS,
                        'reference_type' => $pendingSpin->getMorphClass(),
                        'reference_id' => $pendingSpin->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_LUCKY_WHEEL,
                            'spin_id' => $pendingSpin->id,
                            'idempotency_key' => $data->idempotencyKey,
                            'action' => 'lucky_wheel_reward',
                            'is_jackpot' => $isJackpot,
                        ],
                        'created_at' => now(),
                    ]);
                    $needsFulfillment = false;
                }

                $playResult = $rewardCoins > $cost
                    ? GamePlay::RESULT_WON
                    : ($rewardCoins === $cost && $cost > 0 ? GamePlay::RESULT_DRAW : (
                        $rewardAmount > 0 ? GamePlay::RESULT_WON : GamePlay::RESULT_LOST
                    ));

                $outcome = [
                    'spin_id' => $pendingSpin->id,
                    'sector_id' => $selected->id,
                    'sector_index' => $selected->position,
                    'sector_position' => $selected->position,
                    'label' => $selected->label,
                    'reward_type' => $rewardType,
                    'reward_amount' => $rewardAmount,
                    'reward_reference' => $selected->reward_reference,
                    'is_jackpot' => $isJackpot,
                    'animation' => 'wheel_spin',
                    'config_version' => $config->version,
                    'server_verified' => true,
                ];

                $play = $this->games->createPlay([
                    'game_id' => $game->getKey(),
                    'user_id' => $user->getKey(),
                    'bet_coins' => $cost,
                    'bet_diamonds' => 0,
                    'reward_coins' => $rewardCoins,
                    'reward_diamonds' => $rewardDiamonds,
                    'result' => $playResult,
                    'status' => GamePlay::STATUS_COMPLETED,
                    'input' => [
                        'idempotency_key' => $data->idempotencyKey,
                        'client_session_id' => $data->clientSessionId,
                    ],
                    'outcome' => $outcome,
                    'metadata' => $data->metadata === [] ? null : $data->metadata,
                    'created_at' => now(),
                ]);

                $rewardStatus = $needsFulfillment
                    ? GameReward::STATUS_PENDING_FULFILLMENT
                    : GameReward::STATUS_GRANTED;

                $reward = GameReward::query()->create([
                    'user_id' => $user->getKey(),
                    'game_id' => $game->getKey(),
                    'play_id' => $play->getKey(),
                    'coins' => $rewardCoins,
                    'diamonds' => $rewardDiamonds,
                    'status' => $rewardStatus,
                    'metadata' => [
                        'spin_id' => $pendingSpin->id,
                        'reward_type' => $rewardType,
                        'reward_amount' => $rewardAmount,
                        'reward_reference' => $selected->reward_reference,
                        'requires_fulfillment' => $needsFulfillment,
                        'is_jackpot' => $isJackpot,
                        'sector_label' => $selected->label,
                    ],
                ]);

                $spinStatus = $needsFulfillment
                    ? LuckyWheelSpin::STATUS_FULFILLMENT_PENDING
                    : LuckyWheelSpin::STATUS_SETTLED;

                $pendingSpin->forceFill([
                    'play_id' => $play->getKey(),
                    'reward_id' => $reward->getKey(),
                    'wallet_transaction_id' => $betTxId,
                    'status' => $spinStatus,
                    'outcome' => $outcome,
                    'settled_at' => now(),
                ])->save();

                $freshSpin = $pendingSpin->fresh(['sector', 'config', 'play']);

                return [
                    'spin' => $freshSpin,
                    'wallet' => $wallet->fresh(),
                    'replay' => false,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $spin = $this->luckyWheel->findSpinByIdempotency($user, $data->idempotencyKey);
            if ($spin === null) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Duplicate spin request could not be recovered.',
                ]);
            }

            return [
                'spin' => $spin,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        if (! $result['replay']) {
            LuckyWheelFinished::dispatch($result['spin']);
            LuckyWheelPlayed::dispatch($result['spin']);

            if ($result['spin']->reward_amount > 0 || $result['spin']->is_jackpot) {
                LuckyWheelRewarded::dispatch($result['spin']);
                LuckyWheelRewardWon::dispatch($result['spin']);
            }

            if ($result['spin']->is_jackpot) {
                JackpotWon::dispatch($result['spin']);
                LuckyWheelJackpotWon::dispatch($result['spin']);
            }

            WalletUpdated::dispatch($result['wallet']);
        }

        return $result;
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->luckyWheel->history($user, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50): Collection
    {
        return $this->luckyWheel->leaderboard($period, $limit);
    }

    private function requireActiveConfig(): LuckyWheelConfig
    {
        $config = $this->luckyWheel->activeConfig();
        if ($config === null || ! $config->isActive()) {
            throw ValidationException::withMessages(['config' => 'Lucky Wheel is not configured.']);
        }

        return $config;
    }

    private function assertSpinAllowed(User $user, LuckyWheelConfig $config): void
    {
        if ($config->max_spins_per_day !== null) {
            $today = $this->luckyWheel->countSpinsToday($user, $config->getKey());
            if ($today >= $config->max_spins_per_day) {
                throw ValidationException::withMessages([
                    'spin' => 'Daily spin limit reached.',
                ]);
            }
        }

        if ($config->cooldown_seconds > 0) {
            $last = $this->luckyWheel->lastSpinAt($user, $config->getKey());
            if ($last !== null) {
                $elapsed = $last->diffInSeconds(now());
                if ($elapsed < $config->cooldown_seconds) {
                    throw ValidationException::withMessages([
                        'spin' => 'Please wait before spinning again.',
                        'cooldown_remaining_seconds' => $config->cooldown_seconds - $elapsed,
                    ]);
                }
            }
        }
    }

    private function currencyFor(string $rewardType): ?string
    {
        return match ($rewardType) {
            LuckyWheelSector::REWARD_TYPE_COINS => WalletTransaction::CURRENCY_COINS,
            LuckyWheelSector::REWARD_TYPE_DIAMONDS => WalletTransaction::CURRENCY_DIAMONDS,
            default => null,
        };
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== User::STATUS_ACTIVE) {
            throw new AuthorizationException('Only active registered users can play Lucky Wheel.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                'spin' => 'You are spinning too quickly. Please wait a moment.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
