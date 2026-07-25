<?php

namespace App\Features\Games\Plugins\LuckyDice\Services;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Plugins\LuckyDice\DTOs\LuckyDiceDTO;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceCreated;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceFinished;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceJackpot;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceJackpotWon;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceLost;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDicePlayed;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceRewarded;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceRolling;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceStarted;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceWon;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceConfig;
use App\Features\Games\Plugins\LuckyDice\Repositories\Contracts\LuckyDiceRepository;
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

final readonly class LuckyDiceService
{
    private const PLAY_RATE_KEY = 'lucky-dice-play:%s';

    private const RATE_MAX = 30;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private LuckyDiceRepository $luckyDice,
        private WalletRepository $wallets,
        private GameRepository $games,
        private GameRng $rng,
    ) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        $this->ensureEligible($user);

        $config = $this->requireActiveConfig();
        $playsToday = $this->luckyDice->countBetsToday($user, $config->getKey());
        $lastBetAt = $this->luckyDice->lastBetAt($user, $config->getKey());
        $cooldownRemaining = 0;
        if ($config->cooldown_seconds > 0 && $lastBetAt !== null) {
            $elapsed = $lastBetAt->diffInSeconds(now());
            $cooldownRemaining = max(0, $config->cooldown_seconds - $elapsed);
        }

        return [
            'id' => $config->id,
            'slug' => $config->slug,
            'name' => $config->name,
            'description' => $config->description,
            'version' => $config->version,
            'min_bet_coins' => $config->min_bet_coins,
            'max_bet_coins' => $config->max_bet_coins,
            'default_bet_coins' => $config->default_bet_coins,
            'chip_amounts' => $config->chip_amounts ?? [],
            'max_plays_per_day' => $config->max_plays_per_day,
            'cooldown_seconds' => $config->cooldown_seconds,
            'cooldown_remaining_seconds' => $cooldownRemaining,
            'countdown_seconds' => $config->countdown_seconds,
            'multiplier_big_small' => $config->multiplier_big_small,
            'multiplier_odd_even' => $config->multiplier_odd_even,
            'multiplier_triple' => $config->multiplier_triple,
            'exact_total_multipliers' => $config->exact_total_multipliers ?? [],
            'jackpot_enabled' => $config->jackpot_enabled,
            'jackpot_coins' => $config->jackpot_coins,
            'jackpot_on_triple' => $config->jackpot_on_triple,
            'modes_enabled' => $config->modes_enabled ?? [],
            'status' => $config->status,
            'plays_today' => $playsToday,
            'remaining_plays_today' => $config->max_plays_per_day === null
                ? null
                : max(0, $config->max_plays_per_day - $playsToday),
            'metadata' => $config->metadata,
        ];
    }

    /**
     * @return array{bet: LuckyDiceBet, wallet: Wallet, replay: bool}
     */
    public function play(User $user, LuckyDiceDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::PLAY_RATE_KEY, $user->getKey()));

        if (! Str::isUuid($data->idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency key must be a valid UUID.',
            ]);
        }

        $existing = $this->luckyDice->findBetByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            return [
                'bet' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        $config = $this->requireActiveConfig();
        $this->assertPlayAllowed($user, $config);
        $this->validateBetInput($config, $data);

        $game = Game::query()->where('slug', Game::SLUG_LUCKY_DICE)->first();
        if ($game === null || ! $game->isActive()) {
            throw ValidationException::withMessages(['game' => 'Lucky Dice game is unavailable.']);
        }

        try {
            $result = DB::transaction(function () use ($user, $data, $config, $game): array {
                $existing = LuckyDiceBet::query()
                    ->where('user_id', $user->getKey())
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return [
                        'bet' => $existing->loadMissing(['result', 'config', 'play']),
                        'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                        'replay' => true,
                    ];
                }

                $wallet = $this->wallets->lockForUser($user);
                $betAmount = $data->betAmount;

                if ($wallet->coins_balance < $betAmount) {
                    throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
                }

                $serverSeed = bin2hex(random_bytes(16));
                $pendingBet = $this->luckyDice->createBet([
                    'user_id' => $user->getKey(),
                    'config_id' => $config->getKey(),
                    'game_id' => $game->getKey(),
                    'session_id' => $data->clientSessionId,
                    'idempotency_key' => $data->idempotencyKey,
                    'bet_mode' => $data->betMode,
                    'prediction' => $data->prediction,
                    'bet_amount' => $betAmount,
                    'status' => LuckyDiceBet::STATUS_PENDING,
                    'reward_type' => LuckyDiceBet::REWARD_TYPE_NONE,
                    'reward_amount' => 0,
                    'is_jackpot' => false,
                    'is_win' => false,
                    'config_version' => $config->version,
                    'server_seed' => $serverSeed,
                    'outcome' => null,
                    'metadata' => array_filter([
                        'client_session_id' => $data->clientSessionId,
                        'request_metadata' => $data->metadata === [] ? null : $data->metadata,
                    ]),
                ]);

                LuckyDiceCreated::dispatch($pendingBet);
                LuckyDiceStarted::dispatch($pendingBet);

                // Deduct coins BEFORE roll.
                $wallet->decrement('coins_balance', $betAmount);
                $betTx = WalletTransaction::query()->create([
                    'user_id' => $user->getKey(),
                    'type' => GamePlay::WALLET_TYPE_GAME_BET,
                    'amount' => -$betAmount,
                    'currency' => WalletTransaction::CURRENCY_COINS,
                    'reference_type' => $pendingBet->getMorphClass(),
                    'reference_id' => $pendingBet->getKey(),
                    'metadata' => [
                        'game_slug' => Game::SLUG_LUCKY_DICE,
                        'bet_id' => $pendingBet->id,
                        'idempotency_key' => $data->idempotencyKey,
                        'action' => 'lucky_dice_bet',
                        'bet_mode' => $data->betMode,
                        'prediction' => $data->prediction,
                    ],
                    'created_at' => now(),
                ]);

                $dice1 = $this->rng->int(1, 6);
                $dice2 = $this->rng->int(1, 6);
                $dice3 = $this->rng->int(1, 6);
                $total = $dice1 + $dice2 + $dice3;
                $isTriple = $dice1 === $dice2 && $dice2 === $dice3;

                [$isWin, $multiplier] = $this->evaluateWin($config, $data->betMode, $data->prediction, $dice1, $dice2, $dice3, $total, $isTriple);

                $rewardAmount = 0;
                $rewardType = LuckyDiceBet::REWARD_TYPE_NONE;
                $isJackpot = false;

                if ($isWin) {
                    $rewardAmount = (int) floor($betAmount * $multiplier);
                    $rewardType = LuckyDiceBet::REWARD_TYPE_COINS;

                    if ($isTriple && $config->jackpot_enabled && $config->jackpot_on_triple && $config->jackpot_coins > 0) {
                        $isJackpot = true;
                        $rewardAmount = max($rewardAmount, (int) $config->jackpot_coins);
                        $rewardType = LuckyDiceBet::REWARD_TYPE_JACKPOT;
                    }
                }

                $rewardCoins = 0;
                $rewardDiamonds = 0;
                $needsFulfillment = false;

                if ($rewardType === LuckyDiceBet::REWARD_TYPE_COINS && $rewardAmount > 0) {
                    $wallet->increment('coins_balance', $rewardAmount);
                    $rewardCoins = $rewardAmount;
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                        'amount' => $rewardAmount,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $pendingBet->getMorphClass(),
                        'reference_id' => $pendingBet->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_LUCKY_DICE,
                            'bet_id' => $pendingBet->id,
                            'idempotency_key' => $data->idempotencyKey,
                            'action' => 'lucky_dice_reward',
                            'is_jackpot' => false,
                        ],
                        'created_at' => now(),
                    ]);
                } elseif ($rewardType === LuckyDiceBet::REWARD_TYPE_JACKPOT && $rewardAmount > 0) {
                    $wallet->increment('coins_balance', $rewardAmount);
                    $rewardCoins = $rewardAmount;
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                        'amount' => $rewardAmount,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $pendingBet->getMorphClass(),
                        'reference_id' => $pendingBet->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_LUCKY_DICE,
                            'bet_id' => $pendingBet->id,
                            'idempotency_key' => $data->idempotencyKey,
                            'action' => 'lucky_dice_jackpot',
                            'is_jackpot' => true,
                        ],
                        'created_at' => now(),
                    ]);
                } elseif ($rewardType === LuckyDiceBet::REWARD_TYPE_DIAMONDS && $rewardAmount > 0) {
                    $wallet->increment('diamonds_balance', $rewardAmount);
                    $rewardDiamonds = $rewardAmount;
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                        'amount' => $rewardAmount,
                        'currency' => WalletTransaction::CURRENCY_DIAMONDS,
                        'reference_type' => $pendingBet->getMorphClass(),
                        'reference_id' => $pendingBet->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_LUCKY_DICE,
                            'bet_id' => $pendingBet->id,
                            'idempotency_key' => $data->idempotencyKey,
                            'action' => 'lucky_dice_reward',
                            'is_jackpot' => $isJackpot,
                        ],
                        'created_at' => now(),
                    ]);
                } elseif (in_array($rewardType, [
                    LuckyDiceBet::REWARD_TYPE_GIFT,
                    LuckyDiceBet::REWARD_TYPE_VIP,
                    LuckyDiceBet::REWARD_TYPE_FRAME,
                    LuckyDiceBet::REWARD_TYPE_ENTRY_EFFECT,
                ], true)) {
                    $needsFulfillment = true;
                }

                $playResult = $isWin ? GamePlay::RESULT_WON : GamePlay::RESULT_LOST;

                $outcome = [
                    'bet_id' => $pendingBet->id,
                    'bet_mode' => $data->betMode,
                    'prediction' => $data->prediction,
                    'dice' => [$dice1, $dice2, $dice3],
                    'dice_1' => $dice1,
                    'dice_2' => $dice2,
                    'dice_3' => $dice3,
                    'total' => $total,
                    'is_triple' => $isTriple,
                    'is_win' => $isWin,
                    'multiplier' => $multiplier,
                    'reward_type' => $rewardType,
                    'reward_amount' => $rewardAmount,
                    'is_jackpot' => $isJackpot,
                    'animation' => 'dice_roll',
                    'config_version' => $config->version,
                    'server_verified' => true,
                ];

                $play = $this->games->createPlay([
                    'game_id' => $game->getKey(),
                    'user_id' => $user->getKey(),
                    'bet_coins' => $betAmount,
                    'bet_diamonds' => 0,
                    'reward_coins' => $rewardCoins,
                    'reward_diamonds' => $rewardDiamonds,
                    'result' => $playResult,
                    'status' => GamePlay::STATUS_COMPLETED,
                    'input' => [
                        'idempotency_key' => $data->idempotencyKey,
                        'client_session_id' => $data->clientSessionId,
                        'bet_mode' => $data->betMode,
                        'prediction' => $data->prediction,
                        'bet_amount' => $betAmount,
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
                        'bet_id' => $pendingBet->id,
                        'reward_type' => $rewardType,
                        'reward_amount' => $rewardAmount,
                        'requires_fulfillment' => $needsFulfillment,
                        'is_jackpot' => $isJackpot,
                        'bet_mode' => $data->betMode,
                    ],
                ]);

                $diceResult = $this->luckyDice->createResult([
                    'bet_id' => $pendingBet->getKey(),
                    'user_id' => $user->getKey(),
                    'session_id' => $data->clientSessionId,
                    'dice_1' => $dice1,
                    'dice_2' => $dice2,
                    'dice_3' => $dice3,
                    'total' => $total,
                    'is_triple' => $isTriple,
                    'result' => $isWin ? 'won' : 'lost',
                    'reward_type' => $rewardType,
                    'reward_amount' => $rewardAmount,
                    'status' => 'settled',
                ]);

                $betStatus = $needsFulfillment
                    ? LuckyDiceBet::STATUS_FULFILLMENT_PENDING
                    : LuckyDiceBet::STATUS_SETTLED;

                $pendingBet->forceFill([
                    'play_id' => $play->getKey(),
                    'reward_id' => $reward->getKey(),
                    'wallet_transaction_id' => $betTx->getKey(),
                    'result_id' => $diceResult->getKey(),
                    'status' => $betStatus,
                    'reward_type' => $rewardType,
                    'reward_amount' => $rewardAmount,
                    'reward_currency' => $this->currencyFor($rewardType),
                    'is_jackpot' => $isJackpot,
                    'is_win' => $isWin,
                    'multiplier' => $multiplier,
                    'outcome' => $outcome,
                    'settled_at' => now(),
                ])->save();

                LuckyDiceRolling::dispatch($pendingBet->fresh(['result']));

                $freshBet = $pendingBet->fresh(['result', 'config', 'play']);

                return [
                    'bet' => $freshBet,
                    'wallet' => $wallet->fresh(),
                    'replay' => false,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $bet = $this->luckyDice->findBetByIdempotency($user, $data->idempotencyKey);
            if ($bet === null) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Duplicate bet request could not be recovered.',
                ]);
            }

            return [
                'bet' => $bet,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        if (! $result['replay']) {
            LuckyDiceFinished::dispatch($result['bet']);
            LuckyDicePlayed::dispatch($result['bet']);

            if ($result['bet']->is_win) {
                LuckyDiceWon::dispatch($result['bet']);
            } else {
                LuckyDiceLost::dispatch($result['bet']);
            }

            if ($result['bet']->reward_amount > 0 || $result['bet']->is_jackpot) {
                LuckyDiceRewarded::dispatch($result['bet']);
            }

            if ($result['bet']->is_jackpot) {
                LuckyDiceJackpot::dispatch($result['bet']);
                LuckyDiceJackpotWon::dispatch($result['bet']);
            }

            WalletUpdated::dispatch($result['wallet']);
        }

        return $result;
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->luckyDice->history($user, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50, string $type = 'winners'): Collection
    {
        return $this->luckyDice->leaderboard($period, $limit, $type);
    }

    private function requireActiveConfig(): LuckyDiceConfig
    {
        $config = $this->luckyDice->activeConfig();
        if ($config === null || ! $config->isActive()) {
            throw ValidationException::withMessages(['config' => 'Lucky Dice is not configured.']);
        }

        return $config;
    }

    private function validateBetInput(LuckyDiceConfig $config, LuckyDiceDTO $data): void
    {
        if (! in_array($data->betMode, LuckyDiceBet::MODES, true)) {
            throw ValidationException::withMessages([
                'bet_mode' => 'Invalid bet mode.',
            ]);
        }

        if (! $config->modeEnabled($data->betMode)) {
            throw ValidationException::withMessages([
                'bet_mode' => 'This bet mode is currently disabled.',
            ]);
        }

        if ($data->betAmount < (int) $config->min_bet_coins || $data->betAmount > (int) $config->max_bet_coins) {
            throw ValidationException::withMessages([
                'bet_amount' => "Bet amount must be between {$config->min_bet_coins} and {$config->max_bet_coins} coins.",
            ]);
        }

        $prediction = strtolower(trim($data->prediction));

        match ($data->betMode) {
            LuckyDiceBet::MODE_BIG_SMALL => $this->assertPredictionIn($prediction, ['big', 'small'], 'prediction'),
            LuckyDiceBet::MODE_ODD_EVEN => $this->assertPredictionIn($prediction, ['odd', 'even'], 'prediction'),
            LuckyDiceBet::MODE_EXACT_TOTAL => $this->assertExactTotalPrediction($prediction),
            LuckyDiceBet::MODE_TRIPLE_MATCH => $this->assertTriplePrediction($prediction),
            default => throw ValidationException::withMessages(['bet_mode' => 'Invalid bet mode.']),
        };
    }

    /** @param  list<string>  $allowed */
    private function assertPredictionIn(string $prediction, array $allowed, string $field): void
    {
        if (! in_array($prediction, $allowed, true)) {
            throw ValidationException::withMessages([
                $field => 'Prediction must be one of: '.implode(', ', $allowed).'.',
            ]);
        }
    }

    private function assertExactTotalPrediction(string $prediction): void
    {
        if (! ctype_digit($prediction)) {
            throw ValidationException::withMessages([
                'prediction' => 'Exact total prediction must be an integer from 3 to 18.',
            ]);
        }

        $total = (int) $prediction;
        if ($total < 3 || $total > 18) {
            throw ValidationException::withMessages([
                'prediction' => 'Exact total prediction must be between 3 and 18.',
            ]);
        }
    }

    private function assertTriplePrediction(string $prediction): void
    {
        if ($prediction === 'any') {
            return;
        }

        if (! ctype_digit($prediction) || (int) $prediction < 1 || (int) $prediction > 6) {
            throw ValidationException::withMessages([
                'prediction' => 'Triple match prediction must be "any" or a face from 1 to 6.',
            ]);
        }
    }

    /**
     * @return array{0: bool, 1: float}
     */
    private function evaluateWin(
        LuckyDiceConfig $config,
        string $mode,
        string $prediction,
        int $dice1,
        int $dice2,
        int $dice3,
        int $total,
        bool $isTriple,
    ): array {
        $prediction = strtolower(trim($prediction));

        return match ($mode) {
            LuckyDiceBet::MODE_BIG_SMALL => [
                ($prediction === 'big' && $total >= 11 && $total <= 18)
                    || ($prediction === 'small' && $total >= 3 && $total <= 10),
                (float) $config->multiplier_big_small,
            ],
            LuckyDiceBet::MODE_ODD_EVEN => [
                ($prediction === 'odd' && $total % 2 === 1)
                    || ($prediction === 'even' && $total % 2 === 0),
                (float) $config->multiplier_odd_even,
            ],
            LuckyDiceBet::MODE_EXACT_TOTAL => $this->evaluateExactTotal($config, $prediction, $total),
            LuckyDiceBet::MODE_TRIPLE_MATCH => $this->evaluateTriple($config, $prediction, $dice1, $isTriple),
            default => [false, 0.0],
        };
    }

    /**
     * @return array{0: bool, 1: float}
     */
    private function evaluateExactTotal(LuckyDiceConfig $config, string $prediction, int $total): array
    {
        $won = (int) $prediction === $total;
        if (! $won) {
            return [false, 0.0];
        }

        $map = $config->exact_total_multipliers ?? [];
        $key = (string) $total;
        $multiplier = isset($map[$key]) ? (float) $map[$key] : 1.0;

        return [true, $multiplier];
    }

    /**
     * @return array{0: bool, 1: float}
     */
    private function evaluateTriple(LuckyDiceConfig $config, string $prediction, int $face, bool $isTriple): array
    {
        if (! $isTriple) {
            return [false, 0.0];
        }

        if ($prediction !== 'any' && (int) $prediction !== $face) {
            return [false, 0.0];
        }

        return [true, (float) $config->multiplier_triple];
    }

    private function assertPlayAllowed(User $user, LuckyDiceConfig $config): void
    {
        if ($config->max_plays_per_day !== null) {
            $today = $this->luckyDice->countBetsToday($user, $config->getKey());
            if ($today >= $config->max_plays_per_day) {
                throw ValidationException::withMessages([
                    'play' => 'Daily play limit reached.',
                ]);
            }
        }

        if ($config->cooldown_seconds > 0) {
            $last = $this->luckyDice->lastBetAt($user, $config->getKey());
            if ($last !== null) {
                $elapsed = $last->diffInSeconds(now());
                if ($elapsed < $config->cooldown_seconds) {
                    throw ValidationException::withMessages([
                        'play' => 'Please wait before playing again.',
                        'cooldown_remaining_seconds' => $config->cooldown_seconds - $elapsed,
                    ]);
                }
            }
        }
    }

    private function currencyFor(string $rewardType): ?string
    {
        return match ($rewardType) {
            LuckyDiceBet::REWARD_TYPE_COINS, LuckyDiceBet::REWARD_TYPE_JACKPOT => WalletTransaction::CURRENCY_COINS,
            LuckyDiceBet::REWARD_TYPE_DIAMONDS => WalletTransaction::CURRENCY_DIAMONDS,
            default => null,
        };
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== User::STATUS_ACTIVE) {
            throw new AuthorizationException('Only active registered users can play Lucky Dice.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                'play' => 'You are playing too quickly. Please wait a moment.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
