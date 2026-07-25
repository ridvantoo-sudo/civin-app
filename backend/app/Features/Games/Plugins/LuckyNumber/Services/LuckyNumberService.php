<?php

namespace App\Features\Games\Plugins\LuckyNumber\Services;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Plugins\LuckyNumber\DTOs\LuckyNumberDTO;
use App\Features\Games\Plugins\LuckyNumber\Events\LeaderboardUpdated;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberCreated;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberDrawFinished;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberJackpotWon;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberTicketPurchased;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberWon;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberConfig;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberJackpot;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberResult;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberReward;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use App\Features\Games\Plugins\LuckyNumber\Repositories\Contracts\LuckyNumberRepository;
use App\Features\Games\Plugins\LuckyNumber\Support\LuckyNumberDrawEngine;
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

final readonly class LuckyNumberService
{
    private const PLAY_RATE_KEY = 'lucky-number-play:%s';

    private const RATE_MAX = 30;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private LuckyNumberRepository $luckyNumbers,
        private WalletRepository $wallets,
        private GameRepository $games,
        private GameRng $rng,
        private LuckyNumberDrawEngine $engine,
        private LuckyNumberDrawService $draws,
        private VipRepository $vips,
    ) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        $this->ensureEligible($user);

        $config = $this->requireActiveConfig();
        $ticketsToday = $this->luckyNumbers->countTicketsToday($user, $config->getKey());
        $lastTicketAt = $this->luckyNumbers->lastTicketAt($user, $config->getKey());
        $cooldownRemaining = 0;
        if ($config->cooldown_seconds > 0 && $lastTicketAt !== null) {
            $elapsed = $lastTicketAt->diffInSeconds(now());
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
            'modes_enabled' => $config->modes_enabled ?? LuckyNumberConfig::MODES,
            'number_ranges' => $config->number_ranges ?? LuckyNumberConfig::RANGE_PRESETS,
            'mode_ranges' => $config->mode_ranges ?? [],
            'reward_multipliers' => $config->reward_multipliers ?? [],
            'default_min_number' => $config->default_min_number,
            'default_max_number' => $config->default_max_number,
            'max_picks' => $config->max_picks,
            'draw_count' => $config->draw_count,
            'rtp_percent' => (float) $config->rtp_percent,
            'house_edge_percent' => (float) $config->house_edge_percent,
            'max_tickets_per_day' => $config->max_tickets_per_day,
            'cooldown_seconds' => $config->cooldown_seconds,
            'cooldown_remaining_seconds' => $cooldownRemaining,
            'jackpot_enabled' => $config->jackpot_enabled,
            'bonus_enabled' => $config->bonus_enabled,
            'vip_reward_multiplier' => (float) $config->vip_reward_multiplier,
            'draw_interval_seconds' => $config->draw_interval_seconds,
            'is_vip' => $this->userIsVip($user),
            'jackpots' => $config->jackpots->map(fn (LuckyNumberJackpot $j): array => [
                'id' => $j->id,
                'type' => $j->type,
                'name' => $j->name,
                'pool_coins' => $j->pool_coins,
                'status' => $j->status,
                'requires_vip' => $j->requires_vip,
            ])->values()->all(),
            'status' => $config->status,
            'tickets_today' => $ticketsToday,
            'remaining_tickets_today' => $config->max_tickets_per_day === null
                ? null
                : max(0, $config->max_tickets_per_day - $ticketsToday),
            'metadata' => $config->metadata,
        ];
    }

    /**
     * @return array{ticket: LuckyNumberTicket, wallet: Wallet, replay: bool}
     */
    public function play(User $user, LuckyNumberDTO $data): array
    {
        return $this->executePlay($user, $data, forceAutoPick: false);
    }

    /**
     * Same as play but forces server-side lucky / auto pick numbers.
     *
     * @return array{ticket: LuckyNumberTicket, wallet: Wallet, replay: bool}
     */
    public function quickPick(User $user, LuckyNumberDTO $data): array
    {
        return $this->executePlay($user, $data, forceAutoPick: true);
    }

    /**
     * @return array{ticket: LuckyNumberTicket, wallet: Wallet, replay: bool}
     */
    private function executePlay(User $user, LuckyNumberDTO $data, bool $forceAutoPick): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::PLAY_RATE_KEY, $user->getKey()));

        if (! Str::isUuid($data->idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency key must be a valid UUID.',
            ]);
        }

        $existing = $this->luckyNumbers->findTicketByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            return [
                'ticket' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        $config = $this->requireActiveConfig();
        $this->assertPlayAllowed($user, $config, $data);

        $game = Game::query()->where('slug', Game::SLUG_LUCKY_NUMBER)->first();
        if ($game === null || ! $game->isActive()) {
            throw ValidationException::withMessages(['game' => 'Lucky Number game is unavailable.']);
        }

        $resolved = $this->resolvePicks($user, $config, $data, $forceAutoPick);
        $pickedNumbers = $resolved['picked'];
        $betAmount = $resolved['bet_amount'];
        $autoPick = $resolved['auto_pick'];
        $repeatTicket = $resolved['repeat_ticket'];
        $range = $resolved['range'];
        $drawCount = $resolved['draw_count'];

        if ($betAmount < (int) $config->min_bet_coins || $betAmount > (int) $config->max_bet_coins) {
            throw ValidationException::withMessages([
                'bet_amount' => "Bet must be between {$config->min_bet_coins} and {$config->max_bet_coins}.",
            ]);
        }

        try {
            $result = DB::transaction(function () use (
                $user,
                $data,
                $config,
                $game,
                $pickedNumbers,
                $betAmount,
                $autoPick,
                $repeatTicket,
                $range,
                $drawCount,
            ): array {
                $existing = LuckyNumberTicket::query()
                    ->where('user_id', $user->getKey())
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return [
                        'ticket' => $existing->loadMissing(['config', 'draw', 'play', 'rewards', 'result']),
                        'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                        'replay' => true,
                    ];
                }

                $wallet = $this->wallets->lockForUser($user);
                $cost = $betAmount;

                if ($cost > 0 && $wallet->coins_balance < $cost) {
                    throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
                }

                $draw = $this->draws->createInstantDraw(
                    $config,
                    $data->gameMode,
                    $range['min'],
                    $range['max'],
                    $drawCount,
                    $user,
                );

                $pendingTicket = $this->luckyNumbers->createTicket([
                    'user_id' => $user->getKey(),
                    'config_id' => $config->getKey(),
                    'draw_id' => $draw->getKey(),
                    'game_id' => $game->getKey(),
                    'idempotency_key' => $data->idempotencyKey,
                    'status' => LuckyNumberTicket::STATUS_PENDING,
                    'game_mode' => $data->gameMode,
                    'min_number' => $range['min'],
                    'max_number' => $range['max'],
                    'picked_numbers' => $pickedNumbers,
                    'auto_pick' => $autoPick,
                    'repeat_ticket' => $repeatTicket,
                    'bet_amount' => $betAmount,
                    'cost_coins' => $cost,
                    'reward_coins' => 0,
                    'reward_diamonds' => 0,
                    'is_win' => false,
                    'is_jackpot' => false,
                    'is_bonus' => false,
                    'config_version' => $config->version,
                    'server_seed' => $draw->server_seed,
                    'outcome' => null,
                    'metadata' => array_filter([
                        'client_session_id' => $data->clientSessionId,
                        'request_metadata' => $data->metadata === [] ? null : $data->metadata,
                    ]),
                ]);

                LuckyNumberCreated::dispatch($pendingTicket);
                LuckyNumberTicketPurchased::dispatch($pendingTicket);

                $betTxId = null;
                if ($cost > 0) {
                    $wallet->decrement('coins_balance', $cost);
                    $betTx = WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_BET,
                        'amount' => -$cost,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $pendingTicket->getMorphClass(),
                        'reference_id' => $pendingTicket->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_LUCKY_NUMBER,
                            'ticket_id' => $pendingTicket->id,
                            'idempotency_key' => $data->idempotencyKey,
                            'action' => 'lucky_number_bet',
                            'game_mode' => $data->gameMode,
                        ],
                        'created_at' => now(),
                    ]);
                    $betTxId = $betTx->getKey();
                }

                if ($cost > 0 && $config->jackpot_enabled) {
                    $this->contributeProgressive($config, $cost, $data->gameMode);
                }

                $finishedDraw = $this->draws->runDraw($draw, $pendingTicket, $user);
                $winningNumbers = array_values(array_map('intval', $finishedDraw->winning_numbers ?? []));

                $applyVip = $data->gameMode === LuckyNumberConfig::MODE_VIP_LUCKY_NUMBERS
                    || $this->userIsVip($user);
                $multipliers = $config->reward_multipliers ?? [];
                if ($multipliers === []) {
                    $multipliers = $this->defaultMultipliers((int) $range['max'], (int) $config->max_picks);
                }

                $settlement = $this->engine->settle(
                    $pickedNumbers,
                    $winningNumbers,
                    $betAmount,
                    $multipliers,
                    (float) $config->vip_reward_multiplier,
                    $applyVip && $data->gameMode === LuckyNumberConfig::MODE_VIP_LUCKY_NUMBERS,
                    $data->gameMode,
                );

                $rewardCoins = (int) $settlement['reward_coins'];
                $jackpotTriggered = (bool) $settlement['is_jackpot'];
                $jackpotCoins = 0;
                $jackpotType = null;

                if ($jackpotTriggered || (
                    $config->jackpot_enabled
                    && $data->gameMode === LuckyNumberConfig::MODE_PROGRESSIVE_JACKPOT
                    && (bool) $settlement['is_jackpot']
                )) {
                    [$jackpotType, $jackpotCoins] = $this->awardJackpot(
                        $config,
                        $user,
                        $data->gameMode,
                        $data->metadata['room_id'] ?? null,
                    );
                    $rewardCoins += $jackpotCoins;
                    $jackpotTriggered = $jackpotCoins > 0;
                } elseif (
                    $config->jackpot_enabled
                    && $data->gameMode === LuckyNumberConfig::MODE_DAILY_LUCKY_NUMBER
                    && (int) $settlement['match_count'] > 0
                    && count($pickedNumbers) === count($winningNumbers)
                    && (int) $settlement['match_count'] === count($winningNumbers)
                ) {
                    [$jackpotType, $jackpotCoins] = $this->awardJackpot(
                        $config,
                        $user,
                        $data->gameMode,
                        $data->metadata['room_id'] ?? null,
                    );
                    $rewardCoins += $jackpotCoins;
                    $jackpotTriggered = $jackpotCoins > 0;
                }

                $bonus = $this->applyBonuses(
                    $config,
                    $user,
                    $betAmount,
                    $autoPick || $data->gameMode === LuckyNumberConfig::MODE_LUCKY_PICK,
                    $applyVip,
                );
                $rewardCoins += $bonus['coins'];
                $bonusTriggered = $bonus['triggered'];

                $rewardDiamonds = 0;
                if ($jackpotTriggered && $jackpotCoins >= 10000) {
                    $rewardDiamonds = 5;
                } elseif ($rewardCoins >= $betAmount * 20 && $betAmount > 0) {
                    $rewardDiamonds = 1;
                }

                if ($rewardCoins > 0) {
                    $wallet->increment('coins_balance', $rewardCoins);
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                        'amount' => $rewardCoins,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $pendingTicket->getMorphClass(),
                        'reference_id' => $pendingTicket->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_LUCKY_NUMBER,
                            'ticket_id' => $pendingTicket->id,
                            'idempotency_key' => $data->idempotencyKey,
                            'action' => 'lucky_number_reward',
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
                        'reference_type' => $pendingTicket->getMorphClass(),
                        'reference_id' => $pendingTicket->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_LUCKY_NUMBER,
                            'ticket_id' => $pendingTicket->id,
                            'action' => 'lucky_number_diamond_reward',
                        ],
                        'created_at' => now(),
                    ]);
                }

                $isWin = $rewardCoins > 0 || $rewardDiamonds > 0 || (bool) $settlement['is_win'];
                $playResult = $rewardCoins > $cost
                    ? GamePlay::RESULT_WON
                    : ($rewardCoins === $cost && $cost > 0 ? GamePlay::RESULT_DRAW : (
                        $isWin ? GamePlay::RESULT_WON : GamePlay::RESULT_LOST
                    ));

                // Client-facing outcome: no rolls (audit stays on draw / result).
                $outcome = [
                    'ticket_id' => $pendingTicket->id,
                    'draw_id' => $finishedDraw->id,
                    'picked_numbers' => $pickedNumbers,
                    'winning_numbers' => $winningNumbers,
                    'matched' => $settlement['matched'],
                    'match_count' => $settlement['match_count'],
                    'multiplier' => $settlement['multiplier'],
                    'bonus_coins' => $bonus['coins'],
                    'bonus_types' => $bonus['types'],
                    'jackpot_type' => $jackpotType,
                    'jackpot_coins' => $jackpotCoins,
                    'animation' => 'number_reveal',
                    'config_version' => $config->version,
                    'server_verified' => true,
                    'server_seed_hash' => hash('sha256', (string) $finishedDraw->server_seed),
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
                        'bet_amount' => $betAmount,
                        'picked_numbers' => $pickedNumbers,
                        'auto_pick' => $autoPick,
                        'repeat_ticket' => $repeatTicket,
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
                        'ticket_id' => $pendingTicket->id,
                        'is_jackpot' => $jackpotTriggered,
                        'is_bonus' => $bonusTriggered,
                        'match_count' => $settlement['match_count'],
                    ],
                ]);

                LuckyNumberResult::query()->create([
                    'ticket_id' => $pendingTicket->getKey(),
                    'draw_id' => $finishedDraw->getKey(),
                    'user_id' => $user->getKey(),
                    'config_id' => $config->getKey(),
                    'picked_numbers' => $pickedNumbers,
                    'winning_numbers' => $winningNumbers,
                    'match_count' => $settlement['match_count'],
                    'multiplier' => $settlement['multiplier'],
                    'reward_coins' => $rewardCoins,
                    'is_win' => $isWin,
                    'is_jackpot' => $jackpotTriggered,
                    'audit' => [
                        'server_seed_hash' => hash('sha256', (string) $finishedDraw->server_seed),
                        'matched' => $settlement['matched'],
                        'bonus' => $bonus,
                    ],
                ]);

                LuckyNumberReward::query()->create([
                    'user_id' => $user->getKey(),
                    'ticket_id' => $pendingTicket->getKey(),
                    'config_id' => $config->getKey(),
                    'reward_type' => $jackpotTriggered
                        ? LuckyNumberReward::TYPE_JACKPOT
                        : ($bonusTriggered ? LuckyNumberReward::TYPE_BONUS : LuckyNumberReward::TYPE_COINS),
                    'amount' => $rewardCoins,
                    'currency' => WalletTransaction::CURRENCY_COINS,
                    'is_jackpot' => $jackpotTriggered,
                    'is_bonus' => $bonusTriggered,
                    'is_daily' => in_array('daily_bonus', $bonus['types'], true),
                    'is_vip' => in_array('vip_bonus', $bonus['types'], true)
                        || $data->gameMode === LuckyNumberConfig::MODE_VIP_LUCKY_NUMBERS,
                    'is_streak' => in_array('streak_reward', $bonus['types'], true),
                    'is_referral' => in_array('referral_bonus', $bonus['types'], true),
                    'is_mystery' => in_array('mystery_bonus', $bonus['types'], true),
                    'status' => LuckyNumberReward::STATUS_GRANTED,
                    'metadata' => [
                        'match_count' => $settlement['match_count'],
                        'jackpot_type' => $jackpotType,
                        'bonus_types' => $bonus['types'],
                    ],
                ]);

                $pendingTicket->forceFill([
                    'play_id' => $play->getKey(),
                    'reward_id' => $reward->getKey(),
                    'wallet_transaction_id' => $betTxId,
                    'status' => LuckyNumberTicket::STATUS_SETTLED,
                    'reward_coins' => $rewardCoins,
                    'reward_diamonds' => $rewardDiamonds,
                    'is_win' => $isWin,
                    'is_jackpot' => $jackpotTriggered,
                    'is_bonus' => $bonusTriggered,
                    'jackpot_type' => $jackpotType,
                    'jackpot_coins' => $jackpotCoins,
                    'server_seed' => $finishedDraw->server_seed,
                    'outcome' => $outcome,
                    'settled_at' => now(),
                ])->save();

                return [
                    'ticket' => $pendingTicket->fresh(['config', 'draw', 'play', 'rewards', 'result']),
                    'wallet' => $wallet->fresh(),
                    'replay' => false,
                    'jackpot_triggered' => $jackpotTriggered,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $ticket = $this->luckyNumbers->findTicketByIdempotency($user, $data->idempotencyKey);
            if ($ticket === null) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Duplicate ticket request could not be recovered.',
                ]);
            }

            return [
                'ticket' => $ticket,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        if (! $result['replay']) {
            LuckyNumberDrawFinished::dispatch($result['ticket']);
            if ($result['ticket']->is_win) {
                LuckyNumberWon::dispatch($result['ticket']);
            }
            if ($result['jackpot_triggered'] ?? false) {
                LuckyNumberJackpotWon::dispatch($result['ticket']);
            }
            LeaderboardUpdated::dispatch($result['ticket']);
            WalletUpdated::dispatch($result['wallet']);
        }

        return [
            'ticket' => $result['ticket'],
            'wallet' => $result['wallet'],
            'replay' => $result['replay'],
        ];
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->luckyNumbers->history($user, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50): Collection
    {
        return $this->luckyNumbers->leaderboard($period, $limit);
    }

    /** @return array<string, mixed> */
    public function statistics(User $user): array
    {
        $this->ensureEligible($user);
        $config = $this->requireActiveConfig();
        $stats = $this->luckyNumbers->statistics($user, $config->getKey());

        if ($stats === null) {
            $tickets = LuckyNumberTicket::query()
                ->where('user_id', $user->getKey())
                ->where('config_id', $config->getKey())
                ->where('status', LuckyNumberTicket::STATUS_SETTLED)
                ->get();

            $wagered = (int) $tickets->sum('cost_coins');
            $won = (int) $tickets->sum('reward_coins');
            $wins = $tickets->where('is_win', true)->count();

            return [
                'games_played' => $tickets->count(),
                'tickets_purchased' => $tickets->count(),
                'coins_wagered' => $wagered,
                'coins_won' => $won,
                'diamonds_won' => (int) $tickets->sum('reward_diamonds'),
                'jackpots_won' => $tickets->where('is_jackpot', true)->count(),
                'highest_win' => (int) ($tickets->max('reward_coins') ?? 0),
                'average_win' => $tickets->count() > 0 ? round($won / $tickets->count(), 2) : 0,
                'win_percentage' => $tickets->count() > 0 ? round(($wins / $tickets->count()) * 100, 4) : 0,
                'current_streak' => 0,
                'best_streak' => 0,
            ];
        }

        return [
            'games_played' => $stats->games_played,
            'tickets_purchased' => $stats->tickets_purchased,
            'coins_wagered' => $stats->coins_wagered,
            'coins_won' => $stats->coins_won,
            'diamonds_won' => $stats->diamonds_won,
            'jackpots_won' => $stats->jackpots_won,
            'highest_win' => $stats->highest_win,
            'average_win' => $stats->average_win,
            'win_percentage' => $stats->win_percentage,
            'current_streak' => $stats->current_streak,
            'best_streak' => $stats->best_streak,
        ];
    }

    /** @return array<string, mixed> */
    public function jackpot(User $user): array
    {
        $this->ensureEligible($user);
        $config = $this->requireActiveConfig();
        $jackpots = $this->luckyNumbers->jackpots($config);

        return [
            'enabled' => $config->jackpot_enabled,
            'jackpots' => $jackpots->map(fn (LuckyNumberJackpot $j): array => [
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

    public function draws(?User $user = null, int $perPage = 30): LengthAwarePaginator
    {
        if ($user !== null) {
            $this->ensureEligible($user);
        }

        return $this->luckyNumbers->draws($user, $perPage);
    }

    /** Reset / seed daily jackpot pools. */
    public function processDailyJackpots(): int
    {
        $configs = LuckyNumberConfig::query()
            ->where('status', LuckyNumberConfig::STATUS_ACTIVE)
            ->get();

        $processed = 0;
        foreach ($configs as $config) {
            $existing = LuckyNumberJackpot::query()
                ->where('config_id', $config->getKey())
                ->where('type', LuckyNumberJackpot::TYPE_DAILY)
                ->whereDate('daily_date', now()->toDateString())
                ->first();

            if ($existing !== null) {
                continue;
            }

            $template = LuckyNumberJackpot::query()
                ->where('config_id', $config->getKey())
                ->where('type', LuckyNumberJackpot::TYPE_DAILY)
                ->orderByDesc('created_at')
                ->first();

            $seed = (int) ($template?->seed_coins ?? 5000);

            LuckyNumberJackpot::query()->create([
                'config_id' => $config->getKey(),
                'type' => LuckyNumberJackpot::TYPE_DAILY,
                'name' => 'Daily Lucky Number Jackpot',
                'pool_coins' => $seed,
                'seed_coins' => $seed,
                'min_pool_coins' => $seed,
                'contribution_percent' => (float) ($template?->contribution_percent ?? 0.5),
                'daily_date' => now()->toDateString(),
                'status' => LuckyNumberJackpot::STATUS_ACTIVE,
            ]);
            $processed++;
        }

        return $processed;
    }

    public function processDueDraws(int $limit = 50): int
    {
        return $this->draws->processDueScheduledDraws($limit);
    }

    /**
     * @return array{
     *   picked: list<int>,
     *   bet_amount: int,
     *   auto_pick: bool,
     *   repeat_ticket: bool,
     *   range: array{min: int, max: int},
     *   draw_count: int
     * }
     */
    private function resolvePicks(
        User $user,
        LuckyNumberConfig $config,
        LuckyNumberDTO $data,
        bool $forceAutoPick,
    ): array {
        $range = $config->rangeForMode($data->gameMode);
        $min = $range['min'];
        $max = $range['max'];
        $betAmount = $data->betAmount;
        $autoPick = $forceAutoPick || $data->autoPick || $data->gameMode === LuckyNumberConfig::MODE_LUCKY_PICK;
        $repeatTicket = $data->repeatTicket || $data->gameMode === LuckyNumberConfig::MODE_QUICK_BET;

        $pickCount = $this->pickCountForMode($config, $data->gameMode);
        $drawCount = match ($data->gameMode) {
            LuckyNumberConfig::MODE_SINGLE_NUMBER,
            LuckyNumberConfig::MODE_DAILY_LUCKY_NUMBER => 1,
            LuckyNumberConfig::MODE_PROGRESSIVE_JACKPOT => $pickCount,
            default => max(1, min(max(1, (int) $config->draw_count), $pickCount)),
        };

        if ($repeatTicket) {
            $last = $this->luckyNumbers->lastTicket($user, $config->getKey());
            if ($last === null || ! is_array($last->picked_numbers) || $last->picked_numbers === []) {
                throw ValidationException::withMessages([
                    'repeat_ticket' => 'No previous ticket to repeat.',
                ]);
            }
            $picked = array_values(array_map('intval', $last->picked_numbers));
            if ($data->betAmount <= 0) {
                $betAmount = (int) $last->bet_amount;
            }
            $autoPick = (bool) $last->auto_pick;
            $drawCount = max(1, count($picked) === 1 ? 1 : (int) $config->draw_count);

            return [
                'picked' => $picked,
                'bet_amount' => $betAmount,
                'auto_pick' => $autoPick,
                'repeat_ticket' => true,
                'range' => ['min' => (int) $last->min_number, 'max' => (int) $last->max_number],
                'draw_count' => $drawCount,
            ];
        }

        if ($autoPick) {
            $picked = $this->engine->pickNumbers($min, $max, $pickCount);
        } else {
            $picked = $this->validateClientPicks($data->pickedNumbers, $min, $max, $pickCount, $data->gameMode);
        }

        if ($data->gameMode === LuckyNumberConfig::MODE_PROGRESSIVE_JACKPOT) {
            $drawCount = max(1, count($picked));
        }

        return [
            'picked' => $picked,
            'bet_amount' => $betAmount,
            'auto_pick' => $autoPick,
            'repeat_ticket' => false,
            'range' => $range,
            'draw_count' => $drawCount,
        ];
    }

    private function pickCountForMode(LuckyNumberConfig $config, string $gameMode): int
    {
        return match ($gameMode) {
            LuckyNumberConfig::MODE_SINGLE_NUMBER,
            LuckyNumberConfig::MODE_DAILY_LUCKY_NUMBER => 1,
            LuckyNumberConfig::MODE_MULTIPLE_NUMBERS,
            LuckyNumberConfig::MODE_LUCKY_PICK,
            LuckyNumberConfig::MODE_VIP_LUCKY_NUMBERS,
            LuckyNumberConfig::MODE_PROGRESSIVE_JACKPOT => max(1, (int) $config->max_picks),
            LuckyNumberConfig::MODE_QUICK_BET => max(1, (int) $config->max_picks),
            default => 1,
        };
    }

    /**
     * @param  list<int>|array<int, mixed>  $picked
     * @return list<int>
     */
    private function validateClientPicks(
        array $picked,
        int $min,
        int $max,
        int $expectedCount,
        string $gameMode,
    ): array {
        $numbers = array_values(array_map('intval', $picked));

        if ($gameMode === LuckyNumberConfig::MODE_SINGLE_NUMBER || $expectedCount === 1) {
            if (count($numbers) !== 1) {
                throw ValidationException::withMessages([
                    'picked_numbers' => 'Select exactly one number.',
                ]);
            }
        } else {
            if (count($numbers) < 1 || count($numbers) > $expectedCount) {
                throw ValidationException::withMessages([
                    'picked_numbers' => "Select between 1 and {$expectedCount} numbers.",
                ]);
            }
        }

        if (count($numbers) !== count(array_unique($numbers))) {
            throw ValidationException::withMessages([
                'picked_numbers' => 'Numbers must be unique.',
            ]);
        }

        foreach ($numbers as $n) {
            if ($n < $min || $n > $max) {
                throw ValidationException::withMessages([
                    'picked_numbers' => "Numbers must be between {$min} and {$max}.",
                ]);
            }
        }

        sort($numbers);

        return $numbers;
    }

    /**
     * @return array{coins: int, triggered: bool, types: list<string>}
     */
    private function applyBonuses(
        LuckyNumberConfig $config,
        User $user,
        int $betAmount,
        bool $isLuckyPick,
        bool $isVip,
    ): array {
        if (! $config->bonus_enabled) {
            return ['coins' => 0, 'triggered' => false, 'types' => []];
        }

        $probs = $config->bonus_probabilities ?? [];
        $coins = 0;
        $types = [];

        $checks = [
            'lucky_pick_bonus' => $isLuckyPick,
            'streak_reward' => true,
            'daily_bonus' => true,
            'vip_bonus' => $isVip,
            'referral_bonus' => true,
            'mystery_bonus' => true,
        ];

        foreach ($checks as $key => $eligible) {
            if (! $eligible) {
                continue;
            }

            $chance = (int) ($probs[$key] ?? $probs[$key.'_chance'] ?? 0);
            if ($chance <= 0) {
                continue;
            }

            if ($this->rng->int(1, 100) > $chance) {
                continue;
            }

            $mult = (float) ($probs[$key.'_multiplier'] ?? match ($key) {
                'lucky_pick_bonus' => 1.5,
                'streak_reward' => 2,
                'daily_bonus' => 2,
                'vip_bonus' => (float) $config->vip_reward_multiplier,
                'referral_bonus' => 1.25,
                'mystery_bonus' => 5,
                default => 1,
            });

            $coins += (int) floor($betAmount * $mult);
            $types[] = $key;
        }

        return [
            'coins' => $coins,
            'triggered' => $coins > 0,
            'types' => $types,
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function defaultMultipliers(int $maxNumber, int $maxPicks): array
    {
        $exact = max(2, $maxNumber - 1);
        $multipliers = [
            'single' => $exact,
            'exact' => $exact,
        ];

        for ($i = 1; $i <= max(1, $maxPicks); $i++) {
            $multipliers['match_'.$i] = $i === 1
                ? max(1, (int) floor($exact / max(2, $maxPicks)))
                : (int) max(2, floor($exact * ($i / max(1, $maxPicks))));
        }

        return $multipliers;
    }

    private function contributeProgressive(LuckyNumberConfig $config, int $cost, string $gameMode): void
    {
        $jackpot = $this->luckyNumbers->findJackpot($config, LuckyNumberJackpot::TYPE_PROGRESSIVE);
        if ($jackpot === null) {
            $jackpot = $this->luckyNumbers->findJackpot($config, LuckyNumberJackpot::TYPE_GLOBAL);
        }
        if ($jackpot === null) {
            return;
        }

        $percent = (float) ($jackpot->contribution_percent > 0
            ? $jackpot->contribution_percent
            : $config->progressive_contribution_percent);
        if ($gameMode === LuckyNumberConfig::MODE_PROGRESSIVE_JACKPOT) {
            $percent = max($percent, (float) $config->progressive_contribution_percent);
        }

        $contribution = (int) floor($cost * ($percent / 100));
        if ($contribution < 1) {
            return;
        }

        LuckyNumberJackpot::query()
            ->whereKey($jackpot->getKey())
            ->lockForUpdate()
            ->increment('pool_coins', $contribution);
    }

    /**
     * @return array{0: ?string, 1: int}
     */
    private function awardJackpot(
        LuckyNumberConfig $config,
        User $user,
        string $gameMode,
        ?string $roomId,
    ): array {
        $type = match ($gameMode) {
            LuckyNumberConfig::MODE_DAILY_LUCKY_NUMBER => LuckyNumberJackpot::TYPE_DAILY,
            LuckyNumberConfig::MODE_VIP_LUCKY_NUMBERS => LuckyNumberJackpot::TYPE_VIP,
            LuckyNumberConfig::MODE_PROGRESSIVE_JACKPOT => LuckyNumberJackpot::TYPE_PROGRESSIVE,
            default => LuckyNumberJackpot::TYPE_GLOBAL,
        };

        $jackpot = $this->luckyNumbers->findJackpot($config, $type, $roomId)
            ?? $this->luckyNumbers->findJackpot($config, LuckyNumberJackpot::TYPE_GLOBAL);

        if ($jackpot === null) {
            return [null, 0];
        }

        $locked = LuckyNumberJackpot::query()->whereKey($jackpot->getKey())->lockForUpdate()->first();
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

    private function requireActiveConfig(): LuckyNumberConfig
    {
        $config = $this->luckyNumbers->activeConfig();
        if ($config === null || ! $config->isActive()) {
            throw ValidationException::withMessages(['config' => 'Lucky Number is not configured.']);
        }

        return $config->loadMissing(['jackpots']);
    }

    private function assertPlayAllowed(User $user, LuckyNumberConfig $config, LuckyNumberDTO $data): void
    {
        if (! in_array($data->gameMode, LuckyNumberConfig::MODES, true) || ! $config->modeEnabled($data->gameMode)) {
            throw ValidationException::withMessages(['game_mode' => 'Selected game mode is unavailable.']);
        }

        $isQuickRepeat = $data->gameMode === LuckyNumberConfig::MODE_QUICK_BET || $data->repeatTicket;
        // Quick bet / repeat may inherit bet from last ticket when amount is 0.
        if (! ($isQuickRepeat && $data->betAmount === 0)) {
            if ($data->betAmount < (int) $config->min_bet_coins || $data->betAmount > (int) $config->max_bet_coins) {
                throw ValidationException::withMessages([
                    'bet_amount' => "Bet must be between {$config->min_bet_coins} and {$config->max_bet_coins}.",
                ]);
            }
        }

        if ($data->gameMode === LuckyNumberConfig::MODE_VIP_LUCKY_NUMBERS && ! $this->userIsVip($user)) {
            throw ValidationException::withMessages([
                'game_mode' => 'VIP subscription required for VIP Lucky Numbers.',
            ]);
        }

        if ($config->max_tickets_per_day !== null) {
            $today = $this->luckyNumbers->countTicketsToday($user, $config->getKey());
            if ($today >= $config->max_tickets_per_day) {
                throw ValidationException::withMessages(['ticket' => 'Daily ticket limit reached.']);
            }
        }

        if ($config->cooldown_seconds > 0) {
            $last = $this->luckyNumbers->lastTicketAt($user, $config->getKey());
            if ($last !== null) {
                $elapsed = $last->diffInSeconds(now());
                if ($elapsed < $config->cooldown_seconds) {
                    throw ValidationException::withMessages([
                        'ticket' => 'Please wait before playing again.',
                        'cooldown_remaining_seconds' => $config->cooldown_seconds - $elapsed,
                    ]);
                }
            }
        }
    }

    private function userIsVip(User $user): bool
    {
        $subscription = $this->vips->findForUser($user);

        return $subscription !== null && $subscription->isActive();
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== User::STATUS_ACTIVE) {
            throw new AuthorizationException('Only active registered users can play Lucky Number.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                'ticket' => 'You are playing too quickly. Please wait a moment.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
