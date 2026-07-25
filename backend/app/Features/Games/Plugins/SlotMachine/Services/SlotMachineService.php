<?php

namespace App\Features\Games\Plugins\SlotMachine\Services;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Plugins\SlotMachine\DTOs\SlotMachineDTO;
use App\Features\Games\Plugins\SlotMachine\Events\BonusTriggered;
use App\Features\Games\Plugins\SlotMachine\Events\JackpotWon;
use App\Features\Games\Plugins\SlotMachine\Events\LeaderboardUpdated;
use App\Features\Games\Plugins\SlotMachine\Events\SlotRewardGranted;
use App\Features\Games\Plugins\SlotMachine\Events\SlotSpinFinished;
use App\Features\Games\Plugins\SlotMachine\Events\SlotSpinStarted;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineConfig;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineJackpot;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachinePayline;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineReward;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSession;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSymbol;
use App\Features\Games\Plugins\SlotMachine\Repositories\Contracts\SlotMachineRepository;
use App\Features\Games\Plugins\SlotMachine\Support\SlotSpinEvaluator;
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

final readonly class SlotMachineService
{
    private const PLAY_RATE_KEY = 'slot-machine-play:%s';

    private const RATE_MAX = 30;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private SlotMachineRepository $slots,
        private WalletRepository $wallets,
        private GameRepository $games,
        private GameRng $rng,
        private SlotSpinEvaluator $evaluator,
    ) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        $this->ensureEligible($user);

        $config = $this->requireActiveConfig();
        $spinsToday = $this->slots->countSpinsToday($user, $config->getKey());
        $lastSpinAt = $this->slots->lastSpinAt($user, $config->getKey());
        $cooldownRemaining = 0;
        if ($config->cooldown_seconds > 0 && $lastSpinAt !== null) {
            $elapsed = $lastSpinAt->diffInSeconds(now());
            $cooldownRemaining = max(0, $config->cooldown_seconds - $elapsed);
        }

        $session = $this->slots->findActiveSession($user, $config->getKey());

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
            'modes_enabled' => $config->modes_enabled ?? SlotMachineConfig::MODES,
            'rtp_percent' => (float) $config->rtp_percent,
            'house_edge_percent' => (float) $config->house_edge_percent,
            'classic_columns' => $config->classic_columns,
            'classic_rows' => $config->classic_rows,
            'premium_columns' => $config->premium_columns,
            'premium_rows' => $config->premium_rows,
            'max_spins_per_day' => $config->max_spins_per_day,
            'cooldown_seconds' => $config->cooldown_seconds,
            'cooldown_remaining_seconds' => $cooldownRemaining,
            'daily_free_spins' => $config->daily_free_spins,
            'free_spins_remaining' => (int) ($session?->free_spins_remaining ?? 0),
            'bonus_spins_remaining' => (int) ($session?->bonus_spins_remaining ?? 0),
            'max_auto_spins' => $config->max_auto_spins,
            'max_spin_count' => $config->max_spin_count,
            'jackpot_enabled' => $config->jackpot_enabled,
            'bonus_enabled' => $config->bonus_enabled,
            'vip_reward_multiplier' => (float) $config->vip_reward_multiplier,
            'jackpots' => $config->jackpots->map(fn (SlotMachineJackpot $j): array => [
                'id' => $j->id,
                'type' => $j->type,
                'name' => $j->name,
                'pool_coins' => $j->pool_coins,
                'status' => $j->status,
                'requires_vip' => $j->requires_vip,
            ])->values()->all(),
            'symbols' => $config->symbols->map(fn (SlotMachineSymbol $s): array => [
                'id' => $s->id,
                'code' => $s->code,
                'label' => $s->label,
                'icon' => $s->icon,
                'color' => $s->color,
                'weight' => $s->weight,
                'payout_3' => $s->payout_3,
                'payout_4' => $s->payout_4,
                'payout_5' => $s->payout_5,
                'is_wild' => $s->is_wild,
                'is_scatter' => $s->is_scatter,
                'is_bonus' => $s->is_bonus,
                'is_jackpot' => $s->is_jackpot,
                'is_multiplier' => $s->is_multiplier,
                'multiplier_value' => $s->multiplier_value,
                'status' => $s->status,
            ])->values()->all(),
            'paylines' => $config->paylines->map(fn (SlotMachinePayline $p): array => [
                'id' => $p->id,
                'code' => $p->code,
                'label' => $p->label,
                'layout' => $p->layout,
                'positions' => $p->positions,
                'status' => $p->status,
            ])->values()->all(),
            'status' => $config->status,
            'spins_today' => $spinsToday,
            'remaining_spins_today' => $config->max_spins_per_day === null
                ? null
                : max(0, $config->max_spins_per_day - $spinsToday),
            'metadata' => $config->metadata,
        ];
    }

    /**
     * @return array{spin: SlotMachineSpin, wallet: Wallet, replay: bool}
     */
    public function play(User $user, SlotMachineDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::PLAY_RATE_KEY, $user->getKey()));

        if (! Str::isUuid($data->idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency key must be a valid UUID.',
            ]);
        }

        $existing = $this->slots->findSpinByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            return [
                'spin' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        $config = $this->requireActiveConfig();
        $this->assertPlayAllowed($user, $config, $data);

        $game = Game::query()->where('slug', Game::SLUG_SLOT_MACHINE)->first();
        if ($game === null || ! $game->isActive()) {
            throw ValidationException::withMessages(['game' => 'Slot Machine game is unavailable.']);
        }

        try {
            $result = DB::transaction(function () use ($user, $data, $config, $game): array {
                $existing = SlotMachineSpin::query()
                    ->where('user_id', $user->getKey())
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return [
                        'spin' => $existing->loadMissing(['config', 'play', 'rewards', 'session']),
                        'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                        'replay' => true,
                    ];
                }

                $wallet = $this->wallets->lockForUser($user);
                $session = $this->resolveSession($user, $config, $game, $data);

                $isFreeSpin = $data->gameMode === SlotMachineConfig::MODE_FREE_SPIN
                    || ($session->free_spins_remaining > 0 && $data->gameMode === SlotMachineConfig::MODE_FREE_SPIN);
                $isBonusSpin = $data->gameMode === SlotMachineConfig::MODE_BONUS_SPIN;

                if ($isFreeSpin && $session->free_spins_remaining < 1) {
                    throw ValidationException::withMessages(['game_mode' => 'No free spins remaining.']);
                }
                if ($isBonusSpin && $session->bonus_spins_remaining < 1) {
                    throw ValidationException::withMessages(['game_mode' => 'No bonus spins remaining.']);
                }

                $spinCount = max(1, min($data->spinCount, (int) $config->max_spin_count));
                $unitCost = ($isFreeSpin || $isBonusSpin) ? 0 : $data->betAmount;
                $cost = $unitCost * $spinCount;

                if ($cost > 0 && $wallet->coins_balance < $cost) {
                    throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
                }

                $serverSeed = bin2hex(random_bytes(16));
                $pendingSpin = $this->slots->createSpin([
                    'user_id' => $user->getKey(),
                    'config_id' => $config->getKey(),
                    'session_id' => $session->getKey(),
                    'game_id' => $game->getKey(),
                    'idempotency_key' => $data->idempotencyKey,
                    'status' => SlotMachineSpin::STATUS_PENDING,
                    'game_mode' => $data->gameMode,
                    'bet_amount' => $data->betAmount,
                    'spin_count' => $spinCount,
                    'auto_spin' => $data->autoSpin,
                    'cost_coins' => $cost,
                    'reward_coins' => 0,
                    'reward_diamonds' => 0,
                    'free_spins_awarded' => 0,
                    'is_win' => false,
                    'is_jackpot' => false,
                    'is_bonus' => false,
                    'is_free_spin' => $isFreeSpin,
                    'config_version' => $config->version,
                    'server_seed' => $serverSeed,
                    'outcome' => null,
                    'metadata' => array_filter([
                        'client_session_id' => $data->clientSessionId,
                        'request_metadata' => $data->metadata === [] ? null : $data->metadata,
                    ]),
                ]);

                SlotSpinStarted::dispatch($pendingSpin);

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
                            'game_slug' => Game::SLUG_SLOT_MACHINE,
                            'spin_id' => $pendingSpin->id,
                            'idempotency_key' => $data->idempotencyKey,
                            'action' => 'slot_machine_bet',
                            'game_mode' => $data->gameMode,
                        ],
                        'created_at' => now(),
                    ]);
                    $betTxId = $betTx->getKey();
                }

                // Contribute to progressive jackpot before settlement.
                if ($cost > 0 && $config->jackpot_enabled) {
                    $this->contributeProgressive($config, $cost, $data->gameMode);
                }

                $evaluation = $this->evaluator->evaluate(
                    $config,
                    $config->symbols,
                    $config->paylines,
                    $data->gameMode,
                    $data->betAmount,
                );

                $rewardCoins = (int) $evaluation['reward_coins'];
                $freeSpinsAwarded = (int) $evaluation['free_spins_awarded'];
                $bonusTriggered = (bool) $evaluation['bonus_triggered'];
                $jackpotTriggered = (bool) $evaluation['jackpot_triggered'];
                $jackpotCoins = 0;
                $jackpotType = null;

                if ($jackpotTriggered) {
                    [$jackpotType, $jackpotCoins] = $this->awardJackpot(
                        $config,
                        $user,
                        $data->gameMode,
                        $data->metadata['room_id'] ?? null,
                    );
                    $rewardCoins += $jackpotCoins;
                }

                // Daily reward sprinkle (configurable probability).
                $dailyBonus = 0;
                $dailyChance = (int) (($config->bonus_probabilities ?? [])['daily_reward'] ?? 2);
                if ($dailyChance > 0 && $this->rng->int(1, 100) <= $dailyChance) {
                    $dailyBonus = (int) floor($data->betAmount * (($config->bonus_probabilities ?? [])['daily_multiplier'] ?? 2));
                    $rewardCoins += $dailyBonus;
                }

                $rewardDiamonds = 0;
                if ($jackpotTriggered && $jackpotCoins >= 10000) {
                    $rewardDiamonds = 5;
                } elseif ($rewardCoins >= $data->betAmount * 20) {
                    $rewardDiamonds = 1;
                }

                if ($rewardCoins > 0) {
                    $wallet->increment('coins_balance', $rewardCoins);
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                        'amount' => $rewardCoins,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $pendingSpin->getMorphClass(),
                        'reference_id' => $pendingSpin->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_SLOT_MACHINE,
                            'spin_id' => $pendingSpin->id,
                            'idempotency_key' => $data->idempotencyKey,
                            'action' => 'slot_machine_reward',
                            'is_jackpot' => $jackpotTriggered,
                        ],
                        'created_at' => now(),
                    ]);
                }

                if ($rewardDiamonds > 0) {
                    $wallet->increment('diamonds_balance', $rewardDiamonds);
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                        'amount' => $rewardDiamonds,
                        'currency' => WalletTransaction::CURRENCY_DIAMONDS,
                        'reference_type' => $pendingSpin->getMorphClass(),
                        'reference_id' => $pendingSpin->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_SLOT_MACHINE,
                            'spin_id' => $pendingSpin->id,
                            'action' => 'slot_machine_diamond_reward',
                        ],
                        'created_at' => now(),
                    ]);
                }

                $isWin = $rewardCoins > 0 || $rewardDiamonds > 0 || $freeSpinsAwarded > 0;
                $playResult = $rewardCoins > $cost
                    ? GamePlay::RESULT_WON
                    : ($rewardCoins === $cost && $cost > 0 ? GamePlay::RESULT_DRAW : (
                        $isWin ? GamePlay::RESULT_WON : GamePlay::RESULT_LOST
                    ));

                $outcome = [
                    'spin_id' => $pendingSpin->id,
                    'grid' => $evaluation['grid'],
                    'payline_wins' => $evaluation['payline_wins'],
                    'total_multiplier' => $evaluation['total_multiplier'],
                    'scatter_count' => $evaluation['scatter_count'],
                    'bonus_count' => $evaluation['bonus_count'],
                    'jackpot_symbol_count' => $evaluation['jackpot_symbol_count'],
                    'free_spins_awarded' => $freeSpinsAwarded,
                    'bonus_triggered' => $bonusTriggered,
                    'jackpot_type' => $jackpotType,
                    'jackpot_coins' => $jackpotCoins,
                    'daily_bonus_coins' => $dailyBonus,
                    'animation' => 'slot_spin',
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
                        'game_mode' => $data->gameMode,
                        'bet_amount' => $data->betAmount,
                        'spin_count' => $spinCount,
                        'auto_spin' => $data->autoSpin,
                        'client_session_id' => $data->clientSessionId,
                    ],
                    'outcome' => $outcome,
                    'metadata' => $data->metadata === [] ? null : $data->metadata,
                    'created_at' => now(),
                ]);

                $reward = GameReward::query()->create([
                    'user_id' => $user->getKey(),
                    'game_id' => $game->getKey(),
                    'play_id' => $play->getKey(),
                    'coins' => $rewardCoins,
                    'diamonds' => $rewardDiamonds,
                    'status' => GameReward::STATUS_GRANTED,
                    'metadata' => [
                        'spin_id' => $pendingSpin->id,
                        'is_jackpot' => $jackpotTriggered,
                        'is_bonus' => $bonusTriggered,
                        'free_spins_awarded' => $freeSpinsAwarded,
                    ],
                ]);

                SlotMachineReward::query()->create([
                    'user_id' => $user->getKey(),
                    'spin_id' => $pendingSpin->getKey(),
                    'config_id' => $config->getKey(),
                    'reward_type' => $jackpotTriggered
                        ? SlotMachineReward::TYPE_JACKPOT
                        : ($bonusTriggered ? SlotMachineReward::TYPE_BONUS : SlotMachineReward::TYPE_COINS),
                    'amount' => $rewardCoins,
                    'currency' => WalletTransaction::CURRENCY_COINS,
                    'is_jackpot' => $jackpotTriggered,
                    'is_bonus' => $bonusTriggered,
                    'is_combo' => count($evaluation['payline_wins']) > 1,
                    'is_daily' => $dailyBonus > 0,
                    'is_vip' => $data->gameMode === SlotMachineConfig::MODE_VIP,
                    'status' => SlotMachineReward::STATUS_GRANTED,
                    'metadata' => [
                        'payline_wins' => count($evaluation['payline_wins']),
                        'jackpot_type' => $jackpotType,
                    ],
                ]);

                // Update session free/bonus counters.
                $freeRemaining = (int) $session->free_spins_remaining;
                $bonusRemaining = (int) $session->bonus_spins_remaining;
                if ($isFreeSpin) {
                    $freeRemaining = max(0, $freeRemaining - $spinCount);
                }
                if ($isBonusSpin) {
                    $bonusRemaining = max(0, $bonusRemaining - $spinCount);
                }
                $freeRemaining += $freeSpinsAwarded;
                if ($bonusTriggered) {
                    $bonusRemaining += 5;
                }
                $autoRemaining = (int) $session->auto_spin_remaining;
                if ($data->autoSpin) {
                    $autoRemaining = max(0, $autoRemaining - 1);
                }

                $session->forceFill([
                    'game_mode' => $data->gameMode,
                    'bet_amount' => $data->betAmount,
                    'free_spins_remaining' => $freeRemaining,
                    'bonus_spins_remaining' => $bonusRemaining,
                    'auto_spin_remaining' => $autoRemaining,
                    'last_spin_at' => now(),
                ])->save();

                $pendingSpin->forceFill([
                    'play_id' => $play->getKey(),
                    'reward_id' => $reward->getKey(),
                    'wallet_transaction_id' => $betTxId,
                    'status' => SlotMachineSpin::STATUS_SETTLED,
                    'reward_coins' => $rewardCoins,
                    'reward_diamonds' => $rewardDiamonds,
                    'free_spins_awarded' => $freeSpinsAwarded,
                    'is_win' => $isWin,
                    'is_jackpot' => $jackpotTriggered,
                    'is_bonus' => $bonusTriggered,
                    'jackpot_type' => $jackpotType,
                    'jackpot_coins' => $jackpotCoins,
                    'rolls' => $evaluation['rolls'],
                    'grid' => $evaluation['grid'],
                    'outcome' => $outcome,
                    'settled_at' => now(),
                ])->save();

                return [
                    'spin' => $pendingSpin->fresh(['config', 'play', 'rewards', 'session']),
                    'wallet' => $wallet->fresh(),
                    'replay' => false,
                    'bonus_triggered' => $bonusTriggered,
                    'jackpot_triggered' => $jackpotTriggered,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $spin = $this->slots->findSpinByIdempotency($user, $data->idempotencyKey);
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
            SlotSpinFinished::dispatch($result['spin']);
            if ($result['spin']->reward_coins > 0 || $result['spin']->is_jackpot) {
                SlotRewardGranted::dispatch($result['spin']);
            }
            if ($result['bonus_triggered'] ?? false) {
                BonusTriggered::dispatch($result['spin']);
            }
            if ($result['jackpot_triggered'] ?? false) {
                JackpotWon::dispatch($result['spin']);
            }
            LeaderboardUpdated::dispatch($result['spin']);
            WalletUpdated::dispatch($result['wallet']);
        }

        return [
            'spin' => $result['spin'],
            'wallet' => $result['wallet'],
            'replay' => $result['replay'],
        ];
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->slots->history($user, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50): Collection
    {
        return $this->slots->leaderboard($period, $limit);
    }

    /** @return array<string, mixed> */
    public function statistics(User $user): array
    {
        $this->ensureEligible($user);
        $config = $this->requireActiveConfig();
        $stats = $this->slots->statistics($user, $config->getKey());

        if ($stats === null) {
            // Derive from spins when no row yet.
            $spins = SlotMachineSpin::query()
                ->where('user_id', $user->getKey())
                ->where('config_id', $config->getKey())
                ->where('status', SlotMachineSpin::STATUS_SETTLED)
                ->get();

            $wagered = (int) $spins->sum('cost_coins');
            $won = (int) $spins->sum('reward_coins');

            return [
                'games_played' => $spins->count(),
                'coins_wagered' => $wagered,
                'coins_won' => $won,
                'diamonds_won' => (int) $spins->sum('reward_diamonds'),
                'jackpots' => $spins->where('is_jackpot', true)->count(),
                'bonus_wins' => $spins->where('is_bonus', true)->count(),
                'free_spins_played' => $spins->where('is_free_spin', true)->count(),
                'highest_win' => (int) ($spins->max('reward_coins') ?? 0),
                'average_win' => $spins->count() > 0 ? round($won / $spins->count(), 2) : 0,
                'rtp' => $wagered > 0 ? round(($won / $wagered) * 100, 4) : 0,
            ];
        }

        return [
            'games_played' => $stats->games_played,
            'coins_wagered' => $stats->coins_wagered,
            'coins_won' => $stats->coins_won,
            'diamonds_won' => $stats->diamonds_won,
            'jackpots' => $stats->jackpots,
            'bonus_wins' => $stats->bonus_wins,
            'free_spins_played' => $stats->free_spins_played,
            'highest_win' => $stats->highest_win,
            'average_win' => $stats->average_win,
            'rtp' => $stats->rtp_percent,
        ];
    }

    /** @return array<string, mixed> */
    public function jackpot(User $user): array
    {
        $this->ensureEligible($user);
        $config = $this->requireActiveConfig();
        $jackpots = $this->slots->jackpots($config);

        return [
            'enabled' => $config->jackpot_enabled,
            'jackpots' => $jackpots->map(fn (SlotMachineJackpot $j): array => [
                'id' => $j->id,
                'type' => $j->type,
                'name' => $j->name,
                'pool_coins' => $j->pool_coins,
                'seed_coins' => $j->seed_coins,
                'min_pool_coins' => $j->min_pool_coins,
                'contribution_percent' => (float) $j->contribution_percent,
                'requires_vip' => $j->requires_vip,
                'status' => $j->status,
                'last_won_at' => $j->last_won_at?->toIso8601String(),
                'last_winner_user_id' => $j->last_winner_user_id,
            ])->values()->all(),
        ];
    }

    /** Reset / seed daily jackpot pools. */
    public function processDailyJackpots(): int
    {
        $configs = SlotMachineConfig::query()
            ->where('status', SlotMachineConfig::STATUS_ACTIVE)
            ->get();

        $processed = 0;
        foreach ($configs as $config) {
            $existing = SlotMachineJackpot::query()
                ->where('config_id', $config->getKey())
                ->where('type', SlotMachineJackpot::TYPE_DAILY)
                ->whereDate('daily_date', now()->toDateString())
                ->first();

            if ($existing !== null) {
                continue;
            }

            $template = SlotMachineJackpot::query()
                ->where('config_id', $config->getKey())
                ->where('type', SlotMachineJackpot::TYPE_DAILY)
                ->orderByDesc('created_at')
                ->first();

            $seed = (int) ($template?->seed_coins ?? 5000);

            SlotMachineJackpot::query()->create([
                'config_id' => $config->getKey(),
                'type' => SlotMachineJackpot::TYPE_DAILY,
                'name' => 'Daily Jackpot',
                'pool_coins' => $seed,
                'seed_coins' => $seed,
                'min_pool_coins' => $seed,
                'contribution_percent' => (float) ($template?->contribution_percent ?? 0.5),
                'daily_date' => now()->toDateString(),
                'status' => SlotMachineJackpot::STATUS_ACTIVE,
            ]);
            $processed++;
        }

        return $processed;
    }

    private function resolveSession(
        User $user,
        SlotMachineConfig $config,
        Game $game,
        SlotMachineDTO $data,
    ): SlotMachineSession {
        $session = $this->slots->findActiveSession($user, $config->getKey());
        if ($session !== null) {
            if ($data->autoSpin && $session->auto_spin_remaining < 1) {
                $session->forceFill([
                    'auto_spin_remaining' => min((int) $config->max_auto_spins, max(1, $data->spinCount)),
                ])->save();
            }

            return $session;
        }

        $freeFromDaily = (int) $config->daily_free_spins;

        return $this->slots->createSession([
            'user_id' => $user->getKey(),
            'config_id' => $config->getKey(),
            'game_id' => $game->getKey(),
            'client_session_id' => $data->clientSessionId,
            'game_mode' => $data->gameMode,
            'bet_amount' => $data->betAmount,
            'auto_spin_remaining' => $data->autoSpin
                ? min((int) $config->max_auto_spins, max(1, $data->spinCount))
                : 0,
            'free_spins_remaining' => $freeFromDaily,
            'bonus_spins_remaining' => 0,
            'status' => SlotMachineSession::STATUS_ACTIVE,
            'metadata' => null,
        ]);
    }

    private function contributeProgressive(SlotMachineConfig $config, int $cost, string $gameMode): void
    {
        $jackpot = $this->slots->findJackpot($config, SlotMachineJackpot::TYPE_PROGRESSIVE);
        if ($jackpot === null) {
            $jackpot = $this->slots->findJackpot($config, SlotMachineJackpot::TYPE_GLOBAL);
        }
        if ($jackpot === null) {
            return;
        }

        $percent = (float) ($jackpot->contribution_percent > 0
            ? $jackpot->contribution_percent
            : $config->progressive_contribution_percent);
        if ($gameMode === SlotMachineConfig::MODE_PROGRESSIVE_JACKPOT) {
            $percent = max($percent, (float) $config->progressive_contribution_percent);
        }

        $contribution = (int) floor($cost * ($percent / 100));
        if ($contribution < 1) {
            return;
        }

        SlotMachineJackpot::query()
            ->whereKey($jackpot->getKey())
            ->lockForUpdate()
            ->increment('pool_coins', $contribution);
    }

    /**
     * @return array{0: ?string, 1: int}
     */
    private function awardJackpot(
        SlotMachineConfig $config,
        User $user,
        string $gameMode,
        ?string $roomId,
    ): array {
        $type = match ($gameMode) {
            SlotMachineConfig::MODE_DAILY_JACKPOT => SlotMachineJackpot::TYPE_DAILY,
            SlotMachineConfig::MODE_VIP => SlotMachineJackpot::TYPE_VIP,
            SlotMachineConfig::MODE_PROGRESSIVE_JACKPOT => SlotMachineJackpot::TYPE_PROGRESSIVE,
            default => SlotMachineJackpot::TYPE_GLOBAL,
        };

        $jackpot = $this->slots->findJackpot($config, $type, $roomId)
            ?? $this->slots->findJackpot($config, SlotMachineJackpot::TYPE_GLOBAL);

        if ($jackpot === null) {
            return [null, 0];
        }

        $locked = SlotMachineJackpot::query()->whereKey($jackpot->getKey())->lockForUpdate()->first();
        if ($locked === null || $locked->pool_coins < 1) {
            return [null, 0];
        }

        $amount = (int) $locked->pool_coins;
        $seed = max((int) $locked->seed_coins, (int) $locked->min_pool_coins);

        $locked->forceFill([
            'pool_coins' => $seed,
            'last_won_at' => now(),
            'last_winner_user_id' => $user->getKey(),
        ])->save();

        return [$locked->type, $amount];
    }

    private function requireActiveConfig(): SlotMachineConfig
    {
        $config = $this->slots->activeConfig();
        if ($config === null || ! $config->isActive()) {
            throw ValidationException::withMessages(['config' => 'Slot Machine is not configured.']);
        }

        return $config;
    }

    private function assertPlayAllowed(User $user, SlotMachineConfig $config, SlotMachineDTO $data): void
    {
        if (! in_array($data->gameMode, SlotMachineConfig::MODES, true) || ! $config->modeEnabled($data->gameMode)) {
            throw ValidationException::withMessages(['game_mode' => 'Selected game mode is unavailable.']);
        }

        if ($data->betAmount < (int) $config->min_bet_coins || $data->betAmount > (int) $config->max_bet_coins) {
            throw ValidationException::withMessages([
                'bet_amount' => "Bet must be between {$config->min_bet_coins} and {$config->max_bet_coins}.",
            ]);
        }

        if ($data->spinCount < 1 || $data->spinCount > (int) $config->max_spin_count) {
            throw ValidationException::withMessages([
                'spin_count' => "Spin count must be between 1 and {$config->max_spin_count}.",
            ]);
        }

        if ($config->max_spins_per_day !== null) {
            $today = $this->slots->countSpinsToday($user, $config->getKey());
            if ($today >= $config->max_spins_per_day) {
                throw ValidationException::withMessages(['spin' => 'Daily spin limit reached.']);
            }
        }

        if ($config->cooldown_seconds > 0) {
            $last = $this->slots->lastSpinAt($user, $config->getKey());
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

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== User::STATUS_ACTIVE) {
            throw new AuthorizationException('Only active registered users can play Slot Machine.');
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
