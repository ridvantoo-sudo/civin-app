<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Services;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\JoinRpsTournamentDTO;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchStarted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRewardGranted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRoundStarted;
use App\Features\Games\Plugins\RockPaperScissors\Events\TournamentFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\TournamentStarted;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsBracket;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsReward;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsRound;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsTournament;
use App\Features\Games\Plugins\RockPaperScissors\Repositories\Contracts\RpsRepository;
use App\Features\Games\Plugins\RockPaperScissors\Support\RpsBracketGenerator;
use App\Features\Users\Models\User;
use App\Features\Vip\Repositories\Contracts\VipRepository;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tournament registration, deterministic bracket construction, match spawning,
 * bracket advancement, and prize distribution.
 */
final readonly class RpsTournamentService
{
    private const JOIN_RATE_KEY = 'rps-tournament-join:%s';

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private RpsRepository $matches,
        private WalletRepository $wallets,
        private RpsBracketGenerator $brackets,
        private VipRepository $vips,
    ) {}

    public function list(int $perPage = 30): LengthAwarePaginator
    {
        return RpsTournament::query()
            ->whereIn('status', [
                RpsTournament::STATUS_SCHEDULED,
                RpsTournament::STATUS_REGISTRATION,
                RpsTournament::STATUS_ACTIVE,
                RpsTournament::STATUS_FINISHED,
            ])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'registration' THEN 1 WHEN 'scheduled' THEN 2 ELSE 3 END")
            ->orderBy('scheduled_at')
            ->paginate($perPage);
    }

    /**
     * Register the caller and debit the entry fee. Re-registering is a no-op
     * replay rather than a second charge.
     *
     * @return array{tournament: RpsTournament, wallet: Wallet, replay: bool}
     */
    public function join(User $user, JoinRpsTournamentDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::JOIN_RATE_KEY, $user->getKey()), 30);
        $this->assertUuid($data->idempotencyKey);

        $result = DB::transaction(function () use ($user, $data): array {
            $tournament = RpsTournament::query()->whereKey($data->tournamentId)->lockForUpdate()->first();
            if ($tournament === null) {
                throw ValidationException::withMessages(['tournament_id' => 'Tournament not found.']);
            }

            if ($tournament->hasParticipant((string) $user->getKey())) {
                return [
                    'tournament' => $tournament->fresh(),
                    'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                    'replay' => true,
                ];
            }

            if (! $tournament->isJoinable()) {
                throw ValidationException::withMessages(['tournament_id' => 'Registration is closed.']);
            }

            if ($tournament->registration_closes_at !== null && now()->greaterThan($tournament->registration_closes_at)) {
                throw ValidationException::withMessages(['tournament_id' => 'Registration window has closed.']);
            }

            $participants = $tournament->participants();
            if (count($participants) >= (int) $tournament->max_players) {
                throw ValidationException::withMessages(['tournament_id' => 'Tournament is full.']);
            }

            $this->assertTournamentEligibility($user, $tournament);

            $fee = (int) $tournament->entry_fee_coins;
            $wallet = $this->wallets->lockForUser($user);
            if ($fee > 0 && (int) $wallet->coins_balance < $fee) {
                throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
            }

            $participants[] = (string) $user->getKey();
            $tournament->fill([
                'participant_ids' => array_values(array_unique($participants)),
                'registered_count' => count(array_unique($participants)),
                'reward_pool' => (int) $tournament->reward_pool + $fee,
                'status' => $tournament->status === RpsTournament::STATUS_SCHEDULED
                    ? RpsTournament::STATUS_REGISTRATION
                    : $tournament->status,
            ])->save();

            if ($fee > 0) {
                $wallet->decrement('coins_balance', $fee);
                WalletTransaction::query()->create([
                    'user_id' => $user->getKey(),
                    'type' => GamePlay::WALLET_TYPE_GAME_BET,
                    'amount' => -$fee,
                    'currency' => WalletTransaction::CURRENCY_COINS,
                    'reference_type' => $tournament->getMorphClass(),
                    'reference_id' => $tournament->getKey(),
                    'metadata' => [
                        'game_slug' => Game::SLUG_ROCK_PAPER_SCISSORS,
                        'action' => 'rps_tournament_entry_fee',
                        'tournament_id' => (string) $tournament->getKey(),
                        'idempotency_key' => $data->idempotencyKey,
                    ],
                    'created_at' => now(),
                ]);
            }

            return [
                'tournament' => $tournament->fresh(),
                'wallet' => $wallet->fresh(),
                'replay' => false,
            ];
        });

        if (! $result['replay']) {
            WalletUpdated::dispatch($result['wallet']);
        }

        return $result;
    }

    /**
     * @return array{started: int, brackets_generated: int, matches_spawned: int, brackets_advanced: int, finished: int}
     */
    public function processDue(int $limit = 100): array
    {
        return [
            'started' => $this->startScheduled($limit),
            'brackets_generated' => $this->generateDueBrackets($limit),
            'matches_spawned' => $this->spawnReadyMatches($limit),
            'brackets_advanced' => $this->advanceFinishedBrackets($limit),
            'finished' => $this->finishCompleted($limit),
        ];
    }

    public function startScheduled(int $limit = 100): int
    {
        $count = 0;
        $due = RpsTournament::query()
            ->whereIn('status', [RpsTournament::STATUS_SCHEDULED, RpsTournament::STATUS_REGISTRATION])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        foreach ($due as $tournament) {
            $updated = DB::transaction(function () use ($tournament): ?RpsTournament {
                $locked = RpsTournament::query()->whereKey($tournament->getKey())->lockForUpdate()->first();
                if ($locked === null || ! $locked->isJoinable()) {
                    return null;
                }

                if (count($locked->participants()) < (int) $locked->min_players) {
                    // Not enough entrants by the scheduled time: cancel and refund.
                    $locked->fill([
                        'status' => RpsTournament::STATUS_CANCELLED,
                        'finished_at' => now(),
                    ])->save();
                    $this->refundParticipants($locked->fresh());

                    return null;
                }

                $locked->fill([
                    'status' => RpsTournament::STATUS_ACTIVE,
                    'started_at' => now(),
                    'current_round' => max(1, (int) $locked->current_round),
                    'bracket_seed' => $locked->bracket_seed ?? $this->deriveSeed($locked),
                ])->save();

                return $locked->fresh();
            });

            if ($updated !== null) {
                TournamentStarted::dispatch($updated);
                $count++;
            }
        }

        return $count;
    }

    public function generateDueBrackets(int $limit = 100): int
    {
        $count = 0;
        $active = RpsTournament::query()
            ->where('status', RpsTournament::STATUS_ACTIVE)
            ->where('brackets_generated', false)
            ->limit($limit)
            ->get();

        foreach ($active as $tournament) {
            $generated = DB::transaction(function () use ($tournament): bool {
                $locked = RpsTournament::query()->whereKey($tournament->getKey())->lockForUpdate()->first();
                if ($locked === null || $locked->brackets_generated || $locked->status !== RpsTournament::STATUS_ACTIVE) {
                    return false;
                }

                $this->buildBrackets($locked);

                return true;
            });

            if ($generated) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Persist a deterministic bracket for the tournament. Safe to call only
     * once per tournament; guarded by `brackets_generated`.
     */
    public function buildBrackets(RpsTournament $tournament): Collection
    {
        $participants = $tournament->participants();
        $seed = $tournament->bracket_seed ?? $this->deriveSeed($tournament);
        $nodes = $this->brackets->generate($tournament->format, $participants, $seed);

        $byKey = [];
        foreach ($nodes as $node) {
            $model = RpsBracket::query()->create([
                'tournament_id' => $tournament->getKey(),
                'bracket_type' => $node['bracket_type'],
                'round_index' => $node['round_index'],
                'slot_index' => $node['slot_index'],
                'status' => $node['status'],
                'player_one_id' => $node['player_one_id'],
                'player_two_id' => $node['player_two_id'],
                'winner_id' => $node['winner_id'],
                'is_bye' => $node['is_bye'],
                'metadata' => ['key' => $node['key'], 'seed' => $seed],
            ]);
            $byKey[$node['key']] = $model;
        }

        foreach ($nodes as $node) {
            $model = $byKey[$node['key']] ?? null;
            if ($model === null) {
                continue;
            }

            $model->forceFill([
                'next_bracket_id' => $node['next_key'] === null
                    ? null
                    : ($byKey[$node['next_key']] ?? null)?->getKey(),
                'next_loser_bracket_id' => $node['next_loser_key'] === null
                    ? null
                    : ($byKey[$node['next_loser_key']] ?? null)?->getKey(),
            ])->save();
        }

        $rounds = 0;
        foreach ($nodes as $node) {
            $rounds = max($rounds, (int) $node['round_index'] + 1);
        }

        $tournament->fill([
            'brackets_generated' => true,
            'bracket_seed' => $seed,
            'rounds' => $rounds,
            'current_round' => 1,
        ])->save();

        // Byes advance immediately so round one is playable.
        foreach ($byKey as $bracket) {
            if ($bracket->is_bye && $bracket->winner_id !== null) {
                $this->propagate($bracket->fresh());
            }
        }

        return RpsBracket::query()->where('tournament_id', $tournament->getKey())->get();
    }

    /** Create the actual match rows for bracket slots that have two players. */
    public function spawnReadyMatches(int $limit = 100): int
    {
        $ready = RpsBracket::query()
            ->where('status', RpsBracket::STATUS_READY)
            ->whereNull('match_id')
            ->whereNotNull('player_one_id')
            ->whereNotNull('player_two_id')
            ->limit($limit)
            ->get();

        $spawned = 0;
        foreach ($ready as $candidate) {
            $created = DB::transaction(function () use ($candidate): ?array {
                $bracket = RpsBracket::query()->whereKey($candidate->getKey())->lockForUpdate()->first();
                if ($bracket === null || $bracket->match_id !== null || ! $bracket->isReady()) {
                    return null;
                }

                $tournament = RpsTournament::query()->find($bracket->tournament_id);
                if ($tournament === null || $tournament->status !== RpsTournament::STATUS_ACTIVE) {
                    return null;
                }

                $config = $this->matches->findDefaultConfig();
                $game = Game::query()->where('slug', Game::SLUG_ROCK_PAPER_SCISSORS)->first();
                $mode = $tournament->mode ?: RpsMatch::MODE_TOURNAMENT;
                $now = now();

                $match = $this->matches->createMatch([
                    'config_id' => $config?->getKey(),
                    'game_id' => $game?->getKey(),
                    'tournament_id' => $tournament->getKey(),
                    'bracket_id' => $bracket->getKey(),
                    'challenger_id' => $bracket->player_one_id,
                    'opponent_id' => $bracket->player_two_id,
                    // Deterministic: one match per bracket slot, ever.
                    'idempotency_key' => (string) $bracket->getKey(),
                    'mode' => $mode,
                    'matchmaking' => RpsMatch::MATCHMAKING_TOURNAMENT,
                    'status' => RpsMatch::STATUS_ACTIVE,
                    'visibility' => 'public',
                    'eligibility' => 'everyone',
                    'reward_type' => $tournament->reward_type,
                    // Stakes were collected at registration.
                    'entry_fee_coins' => 0,
                    'pot_coins' => 0,
                    'rounds_target' => $config?->roundsTargetFor($mode) ?? 3,
                    'wins_required' => $config?->winsRequiredFor($mode) ?? 2,
                    'max_rounds' => (int) ($config?->max_rounds ?? 11),
                    'current_round' => 0,
                    'round_timeout_seconds' => (int) ($config?->default_round_timeout_seconds ?? 15),
                    'config_version' => (int) ($config?->version ?? 1),
                    'move_rules' => $config?->moveRules() ?? RpsConfig::DEFAULT_MOVE_RULES,
                    'audit_log' => [[
                        'event' => 'tournament_match_spawned',
                        'at' => $now->toISOString(),
                        'bracket_id' => (string) $bracket->getKey(),
                    ]],
                    'metadata' => [
                        'tournament_slug' => $tournament->slug,
                        'bracket_type' => $bracket->bracket_type,
                        'bracket_round' => (int) $bracket->round_index,
                    ],
                    'started_at' => $now,
                ]);

                $round = $this->matches->createRound([
                    'match_id' => $match->getKey(),
                    'round_index' => 1,
                    'status' => RpsRound::STATUS_ACTIVE,
                    'server_seed' => Str::random(32),
                    'started_at' => $now,
                    'ends_at' => $now->copy()->addSeconds((int) $match->round_timeout_seconds),
                ]);

                $match->fill(['current_round' => 1, 'current_round_id' => $round->getKey()])->save();
                $bracket->fill([
                    'match_id' => $match->getKey(),
                    'status' => RpsBracket::STATUS_ACTIVE,
                ])->save();

                return ['match' => $match->fresh()->loadMissing(['challenger', 'opponent']), 'round' => $round];
            });

            if ($created !== null) {
                $spawned++;
                RpsMatchStarted::dispatch($created['match']);
                RpsRoundStarted::dispatch($created['match'], $created['round']);
            }
        }

        return $spawned;
    }

    /** Move winners (and, in double elimination, losers) into their next slot. */
    public function advanceFinishedBrackets(int $limit = 100): int
    {
        $active = RpsBracket::query()
            ->where('status', RpsBracket::STATUS_ACTIVE)
            ->whereNotNull('match_id')
            ->limit($limit)
            ->get();

        $advanced = 0;
        foreach ($active as $candidate) {
            $done = DB::transaction(function () use ($candidate): bool {
                $bracket = RpsBracket::query()->whereKey($candidate->getKey())->lockForUpdate()->first();
                if ($bracket === null || $bracket->status !== RpsBracket::STATUS_ACTIVE) {
                    return false;
                }

                $match = RpsMatch::query()->find($bracket->match_id);
                if ($match === null || $match->status !== RpsMatch::STATUS_FINISHED) {
                    return false;
                }

                $winnerId = $match->winner_id === null ? null : (string) $match->winner_id;
                if ($winnerId === null) {
                    // A drawn or expired tournament match cannot advance anyone;
                    // seed one is carried forward so the bracket stays playable.
                    $winnerId = (string) $bracket->player_one_id;
                }

                $bracket->fill([
                    'status' => RpsBracket::STATUS_FINISHED,
                    'winner_id' => $winnerId,
                    'loser_id' => (string) $bracket->player_one_id === $winnerId
                        ? $bracket->player_two_id
                        : $bracket->player_one_id,
                ])->save();

                $this->propagate($bracket->fresh());

                return true;
            });

            if ($done) {
                $advanced++;
            }
        }

        return $advanced;
    }

    public function finishCompleted(int $limit = 100): int
    {
        $count = 0;
        $active = RpsTournament::query()
            ->where('status', RpsTournament::STATUS_ACTIVE)
            ->where('brackets_generated', true)
            ->orderBy('started_at')
            ->limit($limit)
            ->get();

        foreach ($active as $tournament) {
            $unfinished = RpsBracket::query()
                ->where('tournament_id', $tournament->getKey())
                ->whereNotIn('status', [RpsBracket::STATUS_FINISHED, RpsBracket::STATUS_BYE])
                ->exists();

            if ($unfinished) {
                continue;
            }

            $updated = DB::transaction(function () use ($tournament): ?RpsTournament {
                $locked = RpsTournament::query()->whereKey($tournament->getKey())->lockForUpdate()->first();
                if ($locked === null || $locked->status !== RpsTournament::STATUS_ACTIVE) {
                    return null;
                }

                $winnerId = $this->determineWinner($locked);
                $locked->fill([
                    'status' => RpsTournament::STATUS_FINISHED,
                    'winner_id' => $winnerId,
                    'finished_at' => now(),
                ])->save();

                $this->distributePrizes($locked->fresh(), $winnerId);

                return $locked->fresh();
            });

            if ($updated !== null) {
                TournamentFinished::dispatch($updated);
                $count++;
            }
        }

        return $count;
    }

    /** Seed the recurring daily tournament if today's edition is missing. */
    public function ensureDailyTournament(): ?RpsTournament
    {
        $slug = 'rps-daily-'.now()->toDateString();
        $existing = RpsTournament::query()->where('slug', $slug)->first();
        if ($existing !== null) {
            return null;
        }

        $config = $this->matches->findDefaultConfig();
        $game = Game::query()->where('slug', Game::SLUG_ROCK_PAPER_SCISSORS)->first();

        return RpsTournament::query()->create([
            'config_id' => $config?->getKey(),
            'game_id' => $game?->getKey(),
            'slug' => $slug,
            'name' => 'Daily RPS Cup '.now()->toDateString(),
            'description' => 'Automatically scheduled daily Rock Paper Scissors cup.',
            'format' => RpsTournament::FORMAT_SINGLE_ELIMINATION,
            'status' => RpsTournament::STATUS_REGISTRATION,
            'mode' => RpsMatch::MODE_BEST_OF_3,
            'max_players' => 16,
            'min_players' => 2,
            'entry_fee_coins' => 0,
            'reward_type' => 'coins',
            'reward_pool' => 0,
            'is_daily' => true,
            'is_scheduled' => true,
            'registration_opens_at' => now(),
            'registration_closes_at' => now()->endOfDay()->subHour(),
            'scheduled_at' => now()->endOfDay()->subHour(),
            'metadata' => ['source' => 'scheduler'],
        ]);
    }

    private function propagate(RpsBracket $bracket): void
    {
        $winnerId = $bracket->winner_id === null ? null : (string) $bracket->winner_id;
        if ($winnerId !== null && $bracket->next_bracket_id !== null) {
            $this->placePlayer((string) $bracket->next_bracket_id, $winnerId);
        }

        $loserId = $bracket->loser_id === null ? null : (string) $bracket->loser_id;
        if ($loserId !== null && $bracket->next_loser_bracket_id !== null) {
            $this->placePlayer((string) $bracket->next_loser_bracket_id, $loserId);
        }
    }

    private function placePlayer(string $bracketId, string $userId): void
    {
        $target = RpsBracket::query()->whereKey($bracketId)->lockForUpdate()->first();
        if ($target === null || $target->status === RpsBracket::STATUS_FINISHED) {
            return;
        }

        if ((string) $target->player_one_id === $userId || (string) $target->player_two_id === $userId) {
            return;
        }

        if ($target->player_one_id === null) {
            $target->player_one_id = $userId;
        } elseif ($target->player_two_id === null) {
            $target->player_two_id = $userId;
        } else {
            return;
        }

        $target->status = $target->isReady() ? RpsBracket::STATUS_READY : RpsBracket::STATUS_PENDING;
        $target->save();
    }

    private function determineWinner(RpsTournament $tournament): ?string
    {
        if ($tournament->format === RpsTournament::FORMAT_ROUND_ROBIN) {
            $tally = [];
            $brackets = RpsBracket::query()->where('tournament_id', $tournament->getKey())->get();
            foreach ($brackets as $bracket) {
                if ($bracket->winner_id === null) {
                    continue;
                }
                $key = (string) $bracket->winner_id;
                $tally[$key] = ($tally[$key] ?? 0) + 1;
            }

            if ($tally === []) {
                return null;
            }

            arsort($tally);

            return (string) array_key_first($tally);
        }

        $final = RpsBracket::query()
            ->where('tournament_id', $tournament->getKey())
            ->orderByRaw("CASE bracket_type WHEN 'final' THEN 0 ELSE 1 END")
            ->orderByDesc('round_index')
            ->orderByDesc('slot_index')
            ->first();

        return $final?->winner_id === null ? null : (string) $final->winner_id;
    }

    private function distributePrizes(RpsTournament $tournament, ?string $winnerId): void
    {
        $pool = (int) $tournament->reward_pool;
        if ($pool <= 0 || $winnerId === null) {
            if ($winnerId === null) {
                $this->refundParticipants($tournament);
            }

            return;
        }

        $structure = $tournament->prize_structure;
        $shares = is_array($structure) && $structure !== [] ? $structure : [['rank' => 1, 'percent' => 100]];

        $ranked = [$winnerId];
        $remaining = $pool;

        foreach ($shares as $index => $share) {
            $userId = $ranked[$index] ?? null;
            if ($userId === null) {
                continue;
            }

            $percent = (float) ($share['percent'] ?? 0);
            $amount = $index === count($shares) - 1
                ? $remaining
                : min($remaining, (int) floor($pool * $percent / 100));
            $remaining -= $amount;

            $user = User::query()->find($userId);
            if ($user === null || $amount <= 0) {
                continue;
            }

            $wallet = $this->wallets->lockForUser($user);
            $wallet->increment('coins_balance', $amount);
            WalletTransaction::query()->create([
                'user_id' => $user->getKey(),
                'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                'amount' => $amount,
                'currency' => WalletTransaction::CURRENCY_COINS,
                'reference_type' => $tournament->getMorphClass(),
                'reference_id' => $tournament->getKey(),
                'metadata' => [
                    'game_slug' => Game::SLUG_ROCK_PAPER_SCISSORS,
                    'action' => 'rps_tournament_prize',
                    'tournament_id' => (string) $tournament->getKey(),
                    'rank' => (int) ($share['rank'] ?? $index + 1),
                ],
                'created_at' => now(),
            ]);

            $reward = RpsReward::query()->create([
                'match_id' => null,
                'tournament_id' => $tournament->getKey(),
                'user_id' => $user->getKey(),
                'config_id' => $tournament->config_id,
                'reward_type' => 'coins',
                'amount' => $amount,
                'currency' => 'coins',
                'rank' => (int) ($share['rank'] ?? $index + 1),
                'is_tournament' => true,
                'status' => RpsReward::STATUS_GRANTED,
                'metadata' => ['pool' => $pool],
            ]);

            $finalMatch = RpsMatch::query()
                ->where('tournament_id', $tournament->getKey())
                ->orderByDesc('finished_at')
                ->first();
            if ($finalMatch !== null) {
                RpsRewardGranted::dispatch($finalMatch, $reward);
            }

            WalletUpdated::dispatch($wallet->fresh());
        }
    }

    private function refundParticipants(RpsTournament $tournament): void
    {
        $fee = (int) $tournament->entry_fee_coins;
        if ($fee <= 0) {
            return;
        }

        foreach ($tournament->participants() as $userId) {
            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            $alreadyRefunded = RpsReward::query()
                ->where('tournament_id', $tournament->getKey())
                ->where('user_id', $user->getKey())
                ->where('is_refund', true)
                ->exists();
            if ($alreadyRefunded) {
                continue;
            }

            $wallet = $this->wallets->lockForUser($user);
            $wallet->increment('coins_balance', $fee);
            WalletTransaction::query()->create([
                'user_id' => $user->getKey(),
                'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                'amount' => $fee,
                'currency' => WalletTransaction::CURRENCY_COINS,
                'reference_type' => $tournament->getMorphClass(),
                'reference_id' => $tournament->getKey(),
                'metadata' => [
                    'game_slug' => Game::SLUG_ROCK_PAPER_SCISSORS,
                    'action' => 'rps_tournament_refund',
                    'tournament_id' => (string) $tournament->getKey(),
                ],
                'created_at' => now(),
            ]);

            RpsReward::query()->create([
                'match_id' => null,
                'tournament_id' => $tournament->getKey(),
                'user_id' => $user->getKey(),
                'config_id' => $tournament->config_id,
                'reward_type' => 'coins',
                'amount' => $fee,
                'currency' => 'coins',
                'is_tournament' => true,
                'is_refund' => true,
                'status' => RpsReward::STATUS_REFUNDED,
                'metadata' => ['action' => 'rps_tournament_refund'],
            ]);
        }
    }

    private function deriveSeed(RpsTournament $tournament): string
    {
        return hash('sha256', implode('|', [
            (string) $tournament->getKey(),
            (string) $tournament->slug,
            $tournament->scheduled_at?->toISOString() ?? '',
        ]));
    }

    private function assertTournamentEligibility(User $user, RpsTournament $tournament): void
    {
        if (! $tournament->is_vip) {
            return;
        }

        $subscription = $this->vips->findForUser($user);
        if ($subscription === null || ! $subscription->isActive()) {
            throw ValidationException::withMessages(['eligibility' => 'VIP required for this tournament.']);
        }
    }

    private function ensureEligible(User $user): void
    {
        if ($user->status !== User::STATUS_ACTIVE || $user->is_guest) {
            throw new AuthorizationException('Not eligible to play Rock Paper Scissors.');
        }
    }

    private function assertUuid(string $value): void
    {
        if (! Str::isUuid($value)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency key must be a valid UUID.',
            ]);
        }
    }

    private function enforceRateLimit(string $key, int $max): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages(['rate' => 'Too many requests. Try again later.']);
        }
        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
