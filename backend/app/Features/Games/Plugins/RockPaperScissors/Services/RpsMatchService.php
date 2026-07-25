<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Services;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\AcceptRpsMatchDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\CreateRpsMatchDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\RematchRpsDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\SubmitRpsMoveDTO;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsChallengeAccepted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsChallengeCreated;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchStarted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMoveSubmitted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRewardGranted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRoundFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRoundStarted;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsHistory;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMove;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsReward;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsRound;
use App\Features\Games\Plugins\RockPaperScissors\Repositories\Contracts\RpsRepository;
use App\Features\Games\Plugins\RockPaperScissors\Support\RpsRulesEngine;
use App\Features\Users\Models\User;
use App\Features\Vip\Repositories\Contracts\VipRepository;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Server-authoritative match lifecycle: challenge, accept, hidden move
 * submission, round resolution, settlement, and timeout handling.
 *
 * Every mutation runs inside a transaction with the match row locked, so
 * concurrent submissions from both players resolve exactly one round.
 */
final readonly class RpsMatchService
{
    private const CREATE_RATE_KEY = 'rps-create:%s';

    private const ACCEPT_RATE_KEY = 'rps-accept:%s';

    private const MOVE_RATE_KEY = 'rps-move:%s';

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private RpsRepository $matches,
        private WalletRepository $wallets,
        private RpsRulesEngine $rules,
        private VipRepository $vips,
    ) {}

    /**
     * Create a challenge. Open matchmaking (random / quick play) pairs the
     * caller into the oldest waiting challenge instead of queueing a new one.
     *
     * @return array{match: RpsMatch, wallet: Wallet, replay: bool, matched: bool}
     */
    public function create(User $user, CreateRpsMatchDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::CREATE_RATE_KEY, $user->getKey()), 20);
        $this->assertUuid($data->idempotencyKey);

        $existing = $this->matches->findMatchByIdempotency($user, $data->idempotencyKey)
            ?? $this->matches->findAcceptedMatchByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            return [
                'match' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
                'matched' => $existing->opponent_id !== null,
            ];
        }

        $config = $this->requireActiveConfig();
        $this->assertCreateAllowed($user, $config, $data);
        $game = $this->requireGame();

        try {
            $result = DB::transaction(function () use ($user, $data, $config, $game): array {
                $duplicate = RpsMatch::query()
                    ->where('challenger_id', $user->getKey())
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return [
                        'match' => $duplicate->loadMissing(['challenger', 'opponent', 'rounds']),
                        'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                        'replay' => true,
                        'matched' => $duplicate->opponent_id !== null,
                        'started' => false,
                        'round' => null,
                    ];
                }

                $fee = max(0, $data->entryFeeCoins);

                if (in_array($data->matchmaking, RpsMatch::OPEN_MATCHMAKING, true)) {
                    $waiting = $this->matches->findOpenChallenge($user, $data->mode, $fee, (string) $config->getKey());
                    if ($waiting !== null) {
                        $joined = $this->joinLocked($user, (string) $waiting->getKey(), $data->idempotencyKey, $config, [
                            'paired_via' => $data->matchmaking,
                        ]);

                        if ($joined !== null) {
                            return $joined + ['replay' => false, 'matched' => true];
                        }
                    }
                }

                $wallet = $this->wallets->lockForUser($user);
                if ($fee > 0 && (int) $wallet->coins_balance < $fee) {
                    throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
                }

                $roundsTarget = $config->roundsTargetFor($data->mode);
                $timeout = $data->roundTimeoutSeconds ?? (int) $config->default_round_timeout_seconds;

                $match = $this->matches->createMatch([
                    'config_id' => $config->getKey(),
                    'game_id' => $game->getKey(),
                    'tournament_id' => $data->tournamentId,
                    'challenger_id' => $user->getKey(),
                    'opponent_id' => $data->opponentId,
                    'idempotency_key' => $data->idempotencyKey,
                    'mode' => $data->mode,
                    'matchmaking' => $data->matchmaking,
                    'status' => in_array($data->matchmaking, RpsMatch::OPEN_MATCHMAKING, true)
                        ? RpsMatch::STATUS_MATCHING
                        : RpsMatch::STATUS_PENDING,
                    'visibility' => $data->visibility,
                    'eligibility' => $data->eligibility,
                    'reward_type' => $data->rewardType,
                    'entry_fee_coins' => $fee,
                    'pot_coins' => $fee,
                    'rounds_target' => $roundsTarget,
                    'wins_required' => $config->winsRequiredFor($data->mode),
                    'max_rounds' => (int) $config->max_rounds,
                    'current_round' => 0,
                    'round_timeout_seconds' => $timeout,
                    'room_id' => $data->roomId,
                    'room_type' => $data->roomType,
                    'is_vip_arena' => $data->mode === RpsMatch::MODE_VIP_ARENA,
                    'config_version' => (int) $config->version,
                    'move_rules' => $this->rules->normalize($config->moveRules()),
                    'whitelist_user_ids' => $data->whitelistUserIds,
                    'audit_log' => [[
                        'event' => 'created',
                        'at' => now()->toISOString(),
                        'user_id' => (string) $user->getKey(),
                    ]],
                    'metadata' => array_filter([
                        'client_match_id' => $data->clientMatchId,
                        'request_metadata' => $data->metadata === [] ? null : $data->metadata,
                        'funded_with' => 'coins',
                    ]),
                    'accept_deadline_at' => now()->addSeconds((int) $config->accept_timeout_seconds),
                ]);

                if ($fee > 0) {
                    $this->debit($wallet, $user, $match, $fee, 'rps_entry_fee_challenger');
                }

                return [
                    'match' => $match->fresh()->loadMissing(['challenger', 'opponent', 'rounds']),
                    'wallet' => $wallet->fresh(),
                    'replay' => false,
                    'matched' => false,
                    'started' => false,
                    'round' => null,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->matches->findMatchByIdempotency($user, $data->idempotencyKey)
                ?? $this->matches->findAcceptedMatchByIdempotency($user, $data->idempotencyKey);
            if ($existing === null) {
                throw ValidationException::withMessages(['idempotency_key' => 'Duplicate request.']);
            }

            return [
                'match' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
                'matched' => $existing->opponent_id !== null,
            ];
        }

        if (! $result['replay']) {
            if ($result['matched']) {
                RpsChallengeAccepted::dispatch($result['match']);
            } else {
                RpsChallengeCreated::dispatch($result['match']);
            }
            if (($result['started'] ?? false) === true) {
                RpsMatchStarted::dispatch($result['match']);
                if ($result['round'] !== null) {
                    RpsRoundStarted::dispatch($result['match'], $result['round']);
                }
            }
            WalletUpdated::dispatch($result['wallet']);
        }

        unset($result['started'], $result['round']);

        return $result;
    }

    /**
     * @return array{match: RpsMatch, wallet: Wallet, replay: bool}
     */
    public function accept(User $user, AcceptRpsMatchDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::ACCEPT_RATE_KEY, $user->getKey()), 60);
        $this->assertUuid($data->idempotencyKey);

        $existing = $this->matches->findAcceptedMatchByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            return [
                'match' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        $config = $this->requireActiveConfig();

        try {
            $result = DB::transaction(function () use ($user, $data, $config): array {
                $joined = $this->joinLocked($user, $data->matchId, $data->idempotencyKey, $config, [
                    'accepted_via' => 'accept_endpoint',
                    'request_metadata' => $data->metadata === [] ? null : $data->metadata,
                ]);

                if ($joined === null) {
                    throw ValidationException::withMessages(['match_id' => 'Challenge is no longer joinable.']);
                }

                return $joined + ['replay' => false];
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->matches->findAcceptedMatchByIdempotency($user, $data->idempotencyKey);
            if ($existing === null) {
                throw ValidationException::withMessages(['idempotency_key' => 'Duplicate request.']);
            }

            return [
                'match' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        RpsChallengeAccepted::dispatch($result['match']);
        RpsMatchStarted::dispatch($result['match']);
        if ($result['round'] !== null) {
            RpsRoundStarted::dispatch($result['match'], $result['round']);
        }
        WalletUpdated::dispatch($result['wallet']);

        unset($result['started'], $result['round'], $result['matched']);

        return $result;
    }

    /**
     * Submit a hidden move for the current round.
     *
     * @return array{match: RpsMatch, round: RpsRound, move: RpsMove, replay: bool, resolved: bool, finished: bool}
     */
    public function move(User $user, SubmitRpsMoveDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::MOVE_RATE_KEY, $user->getKey()), 120);
        $this->assertUuid($data->idempotencyKey);

        $existing = $this->matches->findMoveByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            return [
                'match' => $this->matches->findMatch((string) $existing->match_id) ?? $existing->match,
                'round' => RpsRound::query()->findOrFail($existing->round_id),
                'move' => $existing,
                'replay' => true,
                'resolved' => false,
                'finished' => false,
            ];
        }

        try {
            $result = DB::transaction(function () use ($user, $data): array {
                $match = RpsMatch::query()->whereKey($data->matchId)->lockForUpdate()->first();
                if ($match === null) {
                    throw ValidationException::withMessages(['match_id' => 'Match not found.']);
                }

                $role = $match->roleFor((string) $user->getKey());
                if ($role === null) {
                    throw new AuthorizationException('You are not a player in this match.');
                }

                if (! $match->isPlayable()) {
                    throw ValidationException::withMessages(['match_id' => 'Match is not accepting moves.']);
                }

                $round = $match->current_round_id === null
                    ? null
                    : RpsRound::query()->whereKey($match->current_round_id)->lockForUpdate()->first();

                if ($round === null || $round->status !== RpsRound::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['match_id' => 'No active round.']);
                }

                if ($data->roundId !== null && (string) $round->getKey() !== $data->roundId) {
                    throw ValidationException::withMessages(['round_id' => 'Round is not active.']);
                }

                $now = now();
                if ($round->ends_at !== null && $now->greaterThan($round->ends_at)) {
                    throw ValidationException::withMessages(['round_id' => 'Round timed out.']);
                }

                $alreadySubmitted = $role === RpsMatch::ROLE_CHALLENGER
                    ? (bool) $round->challenger_submitted
                    : (bool) $round->opponent_submitted;
                if ($alreadySubmitted) {
                    throw ValidationException::withMessages(['move' => 'Move already submitted for this round.']);
                }

                $rules = $match->moveRules();
                if (! $this->rules->isValidMove($rules, $data->move)) {
                    throw ValidationException::withMessages(['move' => 'Move is not allowed by the active rule set.']);
                }

                $move = strtolower(trim($data->move));
                $seed = Str::random(32);
                $serverMs = $round->started_at !== null
                    ? (int) $round->started_at->diffInMilliseconds($now)
                    : null;
                $responseMs = $serverMs === null
                    ? $data->clientResponseMs
                    : ($data->clientResponseMs === null
                        ? $serverMs
                        : min($serverMs, max(0, $data->clientResponseMs)));

                $moveRow = $this->matches->createMove([
                    'match_id' => $match->getKey(),
                    'round_id' => $round->getKey(),
                    'user_id' => $user->getKey(),
                    'idempotency_key' => $data->idempotencyKey,
                    'role' => $role,
                    'round_index' => (int) $round->round_index,
                    'move' => $move,
                    'move_hash' => $this->rules->moveHash($move, $seed, (string) $round->getKey(), (string) $user->getKey()),
                    'server_seed' => $seed,
                    'is_auto' => false,
                    'timed_out' => false,
                    'client_response_ms' => $data->clientResponseMs,
                    'response_ms' => $responseMs,
                    'audit' => [
                        'validated_against_rule_set' => true,
                        'hidden_until_resolution' => true,
                        'client_response_ms_trusted' => false,
                    ],
                    'metadata' => $data->metadata === [] ? null : $data->metadata,
                    'submitted_at' => $now,
                ]);

                $isChallenger = $role === RpsMatch::ROLE_CHALLENGER;
                $round->forceFill([
                    ($isChallenger ? 'challenger_submitted' : 'opponent_submitted') => true,
                    ($isChallenger ? 'challenger_move_id' : 'opponent_move_id') => $moveRow->getKey(),
                ])->save();

                $resolution = ['resolved' => false, 'finished' => false, 'next_round' => null];
                if ($round->fresh()->bothSubmitted()) {
                    $resolution = $this->resolveRound($match, $round->fresh());
                }

                return [
                    'match' => $match->fresh()->loadMissing(['challenger', 'opponent', 'rounds', 'rewards']),
                    'round' => $round->fresh(),
                    'move' => $moveRow,
                    'resolved' => $resolution['resolved'],
                    'finished' => $resolution['finished'],
                    'next_round' => $resolution['next_round'],
                    'replay' => false,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->matches->findMoveByIdempotency($user, $data->idempotencyKey);
            if ($existing === null) {
                throw ValidationException::withMessages(['move' => 'Move already submitted for this round.']);
            }

            return [
                'match' => $this->matches->findMatch((string) $existing->match_id) ?? $existing->match,
                'round' => RpsRound::query()->findOrFail($existing->round_id),
                'move' => $existing,
                'replay' => true,
                'resolved' => false,
                'finished' => false,
            ];
        }

        RpsMoveSubmitted::dispatch($result['match'], $result['round'], $result['move']);
        if ($result['resolved']) {
            RpsRoundFinished::dispatch($result['match'], $result['round']);
        }
        if ($result['next_round'] !== null) {
            RpsRoundStarted::dispatch($result['match'], $result['next_round']);
        }
        if ($result['finished']) {
            $this->emitSettlement($result['match']);
        }

        unset($result['next_round']);

        return $result;
    }

    /**
     * Re-challenge the same opponent with the same terms.
     *
     * @return array{match: RpsMatch, wallet: Wallet, replay: bool}
     */
    public function rematch(User $user, RematchRpsDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::CREATE_RATE_KEY, $user->getKey()), 20);
        $this->assertUuid($data->idempotencyKey);

        $existing = $this->matches->findMatchByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            return [
                'match' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        $config = $this->requireActiveConfig();
        $game = $this->requireGame();

        try {
            $result = DB::transaction(function () use ($user, $data, $config, $game): array {
                $previous = RpsMatch::query()->whereKey($data->matchId)->lockForUpdate()->first();
                if ($previous === null) {
                    throw ValidationException::withMessages(['match_id' => 'Match not found.']);
                }

                if ($previous->roleFor((string) $user->getKey()) === null) {
                    throw new AuthorizationException('You are not a player in this match.');
                }

                if (! $previous->isOver()) {
                    throw ValidationException::withMessages(['match_id' => 'Finish the current match first.']);
                }

                if ($previous->tournament_id !== null) {
                    throw ValidationException::withMessages(['match_id' => 'Tournament matches cannot be rematched.']);
                }

                $opponentId = $previous->opponentIdFor((string) $user->getKey());
                if ($opponentId === null) {
                    throw ValidationException::withMessages(['match_id' => 'The match has no opponent to rematch.']);
                }

                $chainCount = (int) $previous->rematch_count + 1;
                if ($chainCount > (int) $config->rematch_limit) {
                    throw ValidationException::withMessages(['match_id' => 'Rematch limit reached for this chain.']);
                }

                $fee = $data->entryFeeCoins ?? (int) $previous->entry_fee_coins;
                $fee = max(0, min($fee, (int) $config->max_entry_fee_coins));

                $wallet = $this->wallets->lockForUser($user);
                if ($fee > 0 && (int) $wallet->coins_balance < $fee) {
                    throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
                }

                $match = $this->matches->createMatch([
                    'config_id' => $config->getKey(),
                    'game_id' => $game->getKey(),
                    'parent_match_id' => $previous->getKey(),
                    'challenger_id' => $user->getKey(),
                    'opponent_id' => $opponentId,
                    'idempotency_key' => $data->idempotencyKey,
                    'mode' => $previous->mode,
                    'matchmaking' => RpsMatch::MATCHMAKING_REMATCH,
                    'status' => RpsMatch::STATUS_PENDING,
                    'visibility' => $previous->visibility,
                    'eligibility' => $previous->eligibility,
                    'reward_type' => $previous->reward_type,
                    'entry_fee_coins' => $fee,
                    'pot_coins' => $fee,
                    'rounds_target' => (int) $previous->rounds_target,
                    'wins_required' => (int) $previous->wins_required,
                    'max_rounds' => (int) $previous->max_rounds,
                    'current_round' => 0,
                    'round_timeout_seconds' => (int) $previous->round_timeout_seconds,
                    'room_id' => $previous->room_id,
                    'room_type' => $previous->room_type,
                    'is_vip_arena' => (bool) $previous->is_vip_arena,
                    'is_rematch' => true,
                    'rematch_count' => $chainCount,
                    'config_version' => (int) $config->version,
                    'move_rules' => $previous->moveRules(),
                    'audit_log' => [[
                        'event' => 'rematch_created',
                        'at' => now()->toISOString(),
                        'user_id' => (string) $user->getKey(),
                        'parent_match_id' => (string) $previous->getKey(),
                    ]],
                    'metadata' => array_filter([
                        'request_metadata' => $data->metadata === [] ? null : $data->metadata,
                        'funded_with' => 'coins',
                    ]),
                    'accept_deadline_at' => now()->addSeconds((int) $config->accept_timeout_seconds),
                ]);

                if ($fee > 0) {
                    $this->debit($wallet, $user, $match, $fee, 'rps_entry_fee_challenger');
                }

                return [
                    'match' => $match->fresh()->loadMissing(['challenger', 'opponent']),
                    'wallet' => $wallet->fresh(),
                    'replay' => false,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->matches->findMatchByIdempotency($user, $data->idempotencyKey);
            if ($existing === null) {
                throw ValidationException::withMessages(['idempotency_key' => 'Duplicate request.']);
            }

            return [
                'match' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        RpsChallengeCreated::dispatch($result['match']);
        WalletUpdated::dispatch($result['wallet']);

        return $result;
    }

    /**
     * Resolve rounds whose clock ran out and expire stale challenges.
     *
     * @return array{rounds_resolved: int, matches_settled: int, challenges_expired: int}
     */
    public function processDue(int $limit = 100): array
    {
        return [
            'rounds_resolved' => $this->processTimedOutRounds($limit),
            'matches_settled' => $this->processStaleMatches($limit),
            'challenges_expired' => $this->expirePendingChallenges($limit),
        ];
    }

    public function processTimedOutRounds(int $limit = 100): int
    {
        $resolved = 0;

        $due = RpsRound::query()
            ->where('status', RpsRound::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->orderBy('ends_at')
            ->limit($limit)
            ->get();

        foreach ($due as $dueRound) {
            $outcome = DB::transaction(function () use ($dueRound): ?array {
                $match = RpsMatch::query()->whereKey($dueRound->match_id)->lockForUpdate()->first();
                if ($match === null || ! $match->isPlayable()) {
                    return null;
                }

                $round = RpsRound::query()->whereKey($dueRound->getKey())->lockForUpdate()->first();
                if ($round === null || $round->status !== RpsRound::STATUS_ACTIVE || ! $round->isExpired()) {
                    return null;
                }

                $this->fillMissingMoves($match, $round);

                return $this->resolveRound($match, $round->fresh());
            });

            if ($outcome === null) {
                continue;
            }

            $match = $this->matches->findMatch((string) $dueRound->match_id);
            $round = RpsRound::query()->find($dueRound->getKey());
            if ($match === null || $round === null) {
                continue;
            }

            $resolved++;
            RpsRoundFinished::dispatch($match, $round);
            if ($outcome['next_round'] !== null) {
                RpsRoundStarted::dispatch($match, $outcome['next_round']);
            }
            if ($outcome['finished']) {
                $this->emitSettlement($match);
            }
        }

        return $resolved;
    }

    /** Force-close matches that blew past the overall match timeout. */
    public function processStaleMatches(int $limit = 100): int
    {
        $config = $this->matches->findDefaultConfig();
        $ttl = (int) ($config?->match_timeout_seconds ?? 1800);

        $stale = RpsMatch::query()
            ->whereIn('status', [RpsMatch::STATUS_ACCEPTED, RpsMatch::STATUS_ACTIVE])
            ->whereNotNull('started_at')
            ->where('started_at', '<=', now()->subSeconds($ttl))
            ->orderBy('started_at')
            ->limit($limit)
            ->get();

        $settled = 0;
        foreach ($stale as $candidate) {
            $done = DB::transaction(function () use ($candidate): bool {
                $match = RpsMatch::query()->whereKey($candidate->getKey())->lockForUpdate()->first();
                if ($match === null || ! $match->isPlayable()) {
                    return false;
                }

                RpsRound::query()
                    ->where('match_id', $match->getKey())
                    ->where('status', RpsRound::STATUS_ACTIVE)
                    ->update(['status' => RpsRound::STATUS_EXPIRED, 'result' => RpsRound::RESULT_NO_CONTEST]);

                $this->appendAudit($match, ['event' => 'match_timeout', 'at' => now()->toISOString()]);
                $this->finishMatch($match, RpsMatch::RESULT_EXPIRED, null);

                return true;
            });

            if ($done) {
                $settled++;
                $match = $this->matches->findMatch((string) $candidate->getKey());
                if ($match !== null) {
                    $this->emitSettlement($match);
                }
            }
        }

        return $settled;
    }

    public function expirePendingChallenges(int $limit = 100): int
    {
        $expired = 0;

        $due = RpsMatch::query()
            ->whereIn('status', [RpsMatch::STATUS_PENDING, RpsMatch::STATUS_MATCHING])
            ->whereNotNull('accept_deadline_at')
            ->where('accept_deadline_at', '<=', now())
            ->orderBy('accept_deadline_at')
            ->limit($limit)
            ->get();

        foreach ($due as $candidate) {
            $done = DB::transaction(function () use ($candidate): bool {
                $match = RpsMatch::query()->whereKey($candidate->getKey())->lockForUpdate()->first();
                if ($match === null || ! $match->isJoinable()) {
                    return false;
                }

                $this->appendAudit($match, ['event' => 'challenge_expired', 'at' => now()->toISOString()]);
                $match->fill([
                    'status' => RpsMatch::STATUS_EXPIRED,
                    'result' => RpsMatch::RESULT_EXPIRED,
                    'finished_at' => now(),
                ])->save();

                $this->refundParticipants($match->fresh(), 'rps_challenge_expired');
                $match->fresh()->fill(['settled_at' => now()])->save();

                return true;
            });

            if ($done) {
                $expired++;
                $match = $this->matches->findMatch((string) $candidate->getKey());
                if ($match !== null) {
                    RpsMatchFinished::dispatch($match);
                }
            }
        }

        return $expired;
    }

    /**
     * Attach `$user` as the opponent of a locked, joinable challenge.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{match: RpsMatch, wallet: Wallet, matched: bool, started: bool, round: RpsRound|null}|null
     */
    private function joinLocked(User $user, string $matchId, string $idempotencyKey, RpsConfig $config, array $metadata = []): ?array
    {
        $match = RpsMatch::query()->whereKey($matchId)->lockForUpdate()->first();
        if ($match === null) {
            throw ValidationException::withMessages(['match_id' => 'Match not found.']);
        }

        if ((string) $match->challenger_id === (string) $user->getKey()) {
            throw ValidationException::withMessages(['match_id' => 'You cannot accept your own challenge.']);
        }

        $reservedForSomeoneElse = $match->opponent_id !== null
            && (string) $match->opponent_id !== (string) $user->getKey();
        if (! $match->isJoinable() || $reservedForSomeoneElse) {
            return null;
        }

        if ($match->accept_deadline_at !== null && now()->greaterThan($match->accept_deadline_at)) {
            throw ValidationException::withMessages(['match_id' => 'Challenge has expired.']);
        }

        $this->assertJoinEligibility($user, $match);

        $wallet = $this->wallets->lockForUser($user);
        $fee = (int) $match->entry_fee_coins;
        if ($fee > 0 && (int) $wallet->coins_balance < $fee) {
            throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
        }

        $now = now();
        $audit = $match->audit_log ?? [];
        $audit[] = [
            'event' => 'accepted',
            'at' => $now->toISOString(),
            'user_id' => (string) $user->getKey(),
        ];

        $match->fill([
            'opponent_id' => $user->getKey(),
            'opponent_idempotency_key' => $idempotencyKey,
            'status' => RpsMatch::STATUS_ACTIVE,
            'pot_coins' => (int) $match->pot_coins + $fee,
            'started_at' => $now,
            'accept_deadline_at' => null,
            'audit_log' => $audit,
            'metadata' => array_merge($match->metadata ?? [], array_filter($metadata)),
        ])->save();

        if ($fee > 0) {
            $this->debit($wallet, $user, $match, $fee, 'rps_entry_fee_opponent');
        }

        $round = $this->startRound($match->fresh(), 1);

        return [
            'match' => $match->fresh()->loadMissing(['challenger', 'opponent', 'rounds']),
            'wallet' => $wallet->fresh(),
            'matched' => true,
            'started' => true,
            'round' => $round,
        ];
    }

    private function startRound(RpsMatch $match, int $index): RpsRound
    {
        $now = now();
        $round = $this->matches->createRound([
            'match_id' => $match->getKey(),
            'round_index' => $index,
            'status' => RpsRound::STATUS_ACTIVE,
            'server_seed' => Str::random(32),
            'started_at' => $now,
            'ends_at' => $now->copy()->addSeconds((int) $match->round_timeout_seconds),
        ]);

        $match->fill([
            'status' => RpsMatch::STATUS_ACTIVE,
            'current_round' => $index,
            'current_round_id' => $round->getKey(),
        ])->save();

        return $round;
    }

    /** Write timed-out placeholder moves for anyone who never submitted. */
    private function fillMissingMoves(RpsMatch $match, RpsRound $round): void
    {
        $roles = [
            RpsMatch::ROLE_CHALLENGER => [(string) $match->challenger_id, 'challenger_submitted', 'challenger_move_id'],
            RpsMatch::ROLE_OPPONENT => [
                $match->opponent_id === null ? null : (string) $match->opponent_id,
                'opponent_submitted',
                'opponent_move_id',
            ],
        ];

        foreach ($roles as $role => [$userId, $submittedColumn, $moveColumn]) {
            if ($userId === null || $round->{$submittedColumn}) {
                continue;
            }

            $move = $this->matches->createMove([
                'match_id' => $match->getKey(),
                'round_id' => $round->getKey(),
                'user_id' => $userId,
                'idempotency_key' => (string) Str::uuid(),
                'role' => $role,
                'round_index' => (int) $round->round_index,
                'move' => null,
                'is_auto' => true,
                'timed_out' => true,
                'audit' => ['reason' => 'round_timeout', 'generated_by' => 'scheduler'],
                'submitted_at' => now(),
            ]);

            $round->forceFill([$submittedColumn => true, $moveColumn => $move->getKey()])->save();

            $missedColumn = $role === RpsMatch::ROLE_CHALLENGER ? 'challenger_missed' : 'opponent_missed';
            $match->forceFill([$missedColumn => (int) $match->{$missedColumn} + 1])->save();
        }
    }

    /**
     * Score a round whose moves are all in, then advance or settle the match.
     *
     * @return array{resolved: bool, finished: bool, next_round: RpsRound|null}
     */
    private function resolveRound(RpsMatch $match, RpsRound $round): array
    {
        $moves = RpsMove::query()->where('round_id', $round->getKey())->get()->keyBy('role');
        $challengerMove = $moves->get(RpsMatch::ROLE_CHALLENGER);
        $opponentMove = $moves->get(RpsMatch::ROLE_OPPONENT);

        $rules = $match->moveRules();
        $comparison = $this->rules->compare($rules, $challengerMove?->move, $opponentMove?->move);

        $tamper = [];
        foreach ([$challengerMove, $opponentMove] as $candidate) {
            if ($candidate === null || $candidate->move === null || $candidate->server_seed === null) {
                continue;
            }
            $valid = $this->rules->verifyMoveHash(
                (string) $candidate->move,
                (string) $candidate->server_seed,
                (string) $round->getKey(),
                (string) $candidate->user_id,
                $candidate->move_hash,
            );
            if (! $valid) {
                $tamper[] = ['move_id' => (string) $candidate->getKey(), 'reason' => 'move_hash_mismatch'];
            }
        }

        $now = now();
        $winnerId = match ($comparison) {
            RpsRulesEngine::CHALLENGER_WINS => (string) $match->challenger_id,
            RpsRulesEngine::OPPONENT_WINS => $match->opponent_id === null ? null : (string) $match->opponent_id,
            default => null,
        };

        $bothMissing = ($challengerMove?->move === null) && ($opponentMove?->move === null);

        $round->fill([
            'status' => RpsRound::STATUS_RESOLVED,
            'result' => match (true) {
                $bothMissing => RpsRound::RESULT_NO_CONTEST,
                $comparison === RpsRulesEngine::CHALLENGER_WINS => RpsRound::RESULT_CHALLENGER,
                $comparison === RpsRulesEngine::OPPONENT_WINS => RpsRound::RESULT_OPPONENT,
                default => RpsRound::RESULT_DRAW,
            },
            'winner_id' => $winnerId,
            'challenger_move' => $challengerMove?->move,
            'opponent_move' => $opponentMove?->move,
            'revealed' => true,
            'revealed_at' => $now,
            'resolved_at' => $now,
            'audit' => [
                'rule_set' => $rules,
                'comparison' => $comparison,
                'challenger_timed_out' => (bool) ($challengerMove?->timed_out ?? false),
                'opponent_timed_out' => (bool) ($opponentMove?->timed_out ?? false),
                'tamper_findings' => $tamper,
            ],
        ])->save();

        RpsMove::query()
            ->where('round_id', $round->getKey())
            ->update(['revealed_at' => $now]);

        $challengerScore = (int) $match->challenger_score + ($comparison === RpsRulesEngine::CHALLENGER_WINS ? 1 : 0);
        $opponentScore = (int) $match->opponent_score + ($comparison === RpsRulesEngine::OPPONENT_WINS ? 1 : 0);
        $drawCount = (int) $match->draw_count + ($comparison === RpsRulesEngine::DRAW ? 1 : 0);
        $roundsPlayed = (int) $match->rounds_played + 1;

        $match->fill([
            'challenger_score' => $challengerScore,
            'opponent_score' => $opponentScore,
            'draw_count' => $drawCount,
            'rounds_played' => $roundsPlayed,
            'tamper_flags' => $tamper === [] ? $match->tamper_flags : array_merge($match->tamper_flags ?? [], $tamper),
        ])->save();

        $config = $this->matches->findDefaultConfig();
        $forfeitThreshold = (int) ($config?->forfeit_after_missed_rounds ?? 2);
        $challengerForfeits = (int) $match->challenger_missed >= $forfeitThreshold;
        $opponentForfeits = $match->opponent_id !== null && (int) $match->opponent_missed >= $forfeitThreshold;

        $winsRequired = max(1, (int) $match->wins_required);
        $result = match (true) {
            $challengerForfeits && ! $opponentForfeits => RpsMatch::RESULT_OPPONENT_WON,
            $opponentForfeits && ! $challengerForfeits => RpsMatch::RESULT_CHALLENGER_WON,
            $challengerForfeits && $opponentForfeits => RpsMatch::RESULT_DRAW,
            $challengerScore >= $winsRequired => RpsMatch::RESULT_CHALLENGER_WON,
            $opponentScore >= $winsRequired => RpsMatch::RESULT_OPPONENT_WON,
            $roundsPlayed >= (int) $match->max_rounds => match (true) {
                $challengerScore > $opponentScore => RpsMatch::RESULT_CHALLENGER_WON,
                $opponentScore > $challengerScore => RpsMatch::RESULT_OPPONENT_WON,
                default => RpsMatch::RESULT_DRAW,
            },
            default => null,
        };

        if ($result === null) {
            $next = $this->startRound($match->fresh(), $roundsPlayed + 1);

            return ['resolved' => true, 'finished' => false, 'next_round' => $next];
        }

        $forfeitedBy = match (true) {
            $challengerForfeits && ! $opponentForfeits => (string) $match->challenger_id,
            $opponentForfeits && ! $challengerForfeits => $match->opponent_id === null ? null : (string) $match->opponent_id,
            default => null,
        };

        $this->finishMatch($match->fresh(), $result, $forfeitedBy);

        return ['resolved' => true, 'finished' => true, 'next_round' => null];
    }

    /**
     * Terminal state transition plus wallet settlement. Runs inside the
     * caller's transaction with the match already locked.
     */
    private function finishMatch(RpsMatch $match, string $result, ?string $forfeitedBy): void
    {
        if ($match->status === RpsMatch::STATUS_FINISHED) {
            return;
        }

        $winnerId = match ($result) {
            RpsMatch::RESULT_CHALLENGER_WON => (string) $match->challenger_id,
            RpsMatch::RESULT_OPPONENT_WON => $match->opponent_id === null ? null : (string) $match->opponent_id,
            default => null,
        };
        $loserId = $winnerId === null ? null : $match->opponentIdFor($winnerId);

        $match->fill([
            'status' => RpsMatch::STATUS_SETTLING,
            'result' => $forfeitedBy !== null ? RpsMatch::RESULT_FORFEIT : $result,
            'winner_id' => $winnerId,
            'loser_id' => $loserId,
            'forfeited_by' => $forfeitedBy,
            'finished_at' => now(),
        ])->save();

        $this->settle($match->fresh(), $winnerId, $result);
    }

    private function settle(RpsMatch $match, ?string $winnerId, string $result): void
    {
        $config = $this->matches->findDefaultConfig();
        $game = Game::query()->where('slug', Game::SLUG_ROCK_PAPER_SCISSORS)->first();
        $fee = (int) $match->entry_fee_coins;
        $participants = $match->participantIds();

        // Nobody won, or the match never got a second player: return every stake.
        if ($winnerId === null) {
            $this->refundParticipants($match, 'rps_settlement_refund');
            foreach ($participants as $userId) {
                $this->writeHistory($match, $userId, $game, null, 0, 0, false, $result);
            }
            $match->fill([
                'status' => RpsMatch::STATUS_FINISHED,
                'settled_at' => now(),
            ])->save();

            return;
        }

        $pot = $fee * count($participants);
        $rakeBps = (int) ($config?->house_rake_bps ?? 0);
        $rake = (int) floor($pot * $rakeBps / 10000);
        $payout = max(0, $pot - $rake);

        $winner = User::query()->find($winnerId);
        if ($winner === null) {
            $this->refundParticipants($match, 'rps_settlement_refund');
            $match->fill(['status' => RpsMatch::STATUS_FINISHED, 'settled_at' => now()])->save();

            return;
        }

        $isVip = $this->userIsVip($winner);
        $vipBonus = 0;
        if ($isVip && $payout > 0) {
            $multiplier = (float) ($config?->vip_reward_multiplier ?? 1.0);
            $vipBonus = max(0, (int) round($payout * $multiplier) - $payout);
        }
        $coinReward = $payout + $vipBonus;
        $diamondReward = $match->reward_type === 'diamonds' ? $coinReward : 0;
        if ($diamondReward > 0) {
            // Diamond arenas pay the pot out in diamonds instead of coins.
            $coinReward = 0;
        }

        $wallet = $this->wallets->lockForUser($winner);

        try {
            $play = $game === null ? null : GamePlay::query()->create([
                'user_id' => $winner->getKey(),
                'game_id' => $game->getKey(),
                'bet_coins' => $fee,
                'reward_coins' => $coinReward,
                'reward_diamonds' => $diamondReward,
                'result' => GamePlay::RESULT_WON,
                'outcome' => [
                    'match_id' => (string) $match->getKey(),
                    'mode' => $match->mode,
                    'challenger_score' => (int) $match->challenger_score,
                    'opponent_score' => (int) $match->opponent_score,
                    'server_verified' => true,
                ],
                'status' => GamePlay::STATUS_COMPLETED,
                'created_at' => now(),
            ]);

            if ($coinReward > 0) {
                $wallet->increment('coins_balance', $coinReward);
                $this->writeWalletTransaction(
                    $winner,
                    $match,
                    $coinReward,
                    WalletTransaction::CURRENCY_COINS,
                    GamePlay::WALLET_TYPE_GAME_REWARD,
                    'rps_match_reward',
                );
            }

            if ($diamondReward > 0) {
                $wallet->increment('diamonds_balance', $diamondReward);
                $this->writeWalletTransaction(
                    $winner,
                    $match,
                    $diamondReward,
                    WalletTransaction::CURRENCY_DIAMONDS,
                    GamePlay::WALLET_TYPE_GAME_REWARD,
                    'rps_match_reward_diamonds',
                );
            }

            $reward = RpsReward::query()->create([
                'match_id' => $match->getKey(),
                'tournament_id' => $match->tournament_id,
                'user_id' => $winner->getKey(),
                'config_id' => $match->config_id,
                'reward_type' => $diamondReward > 0 ? 'diamonds' : 'coins',
                'amount' => $coinReward + $diamondReward,
                'currency' => $diamondReward > 0 ? 'diamonds' : 'coins',
                'rank' => 1,
                'is_vip' => $vipBonus > 0,
                'is_tournament' => $match->tournament_id !== null,
                'status' => RpsReward::STATUS_GRANTED,
                'metadata' => [
                    'pot_coins' => $pot,
                    'rake_coins' => $rake,
                    'vip_bonus_coins' => $vipBonus,
                ],
            ]);

            $match->fill(['rake_coins' => $rake])->save();

            if ($game !== null) {
                GameReward::query()->create([
                    'user_id' => $winner->getKey(),
                    'game_id' => $game->getKey(),
                    'play_id' => $play?->getKey(),
                    'coins' => $coinReward,
                    'diamonds' => $diamondReward,
                    'status' => GameReward::STATUS_GRANTED,
                    'metadata' => [
                        'rps_reward_id' => (string) $reward->getKey(),
                        'match_id' => (string) $match->getKey(),
                    ],
                ]);
            }

            RpsRewardGranted::dispatch($match, $reward);

            // Perks like frames or VIP days are recorded now and fulfilled by
            // the reward pipeline; the coin pot has already settled above.
            if (! in_array($match->reward_type, RpsConfig::WALLET_REWARD_TYPES, true)) {
                $perk = RpsReward::query()->create([
                    'match_id' => $match->getKey(),
                    'tournament_id' => $match->tournament_id,
                    'user_id' => $winner->getKey(),
                    'config_id' => $match->config_id,
                    'reward_type' => $match->reward_type,
                    'amount' => 0,
                    'currency' => null,
                    'rank' => 1,
                    'is_vip' => $isVip,
                    'is_tournament' => $match->tournament_id !== null,
                    'status' => RpsReward::STATUS_PENDING,
                    'metadata' => ['non_wallet' => true, 'awaiting_fulfillment' => true],
                ]);

                if ($game !== null) {
                    GameReward::query()->create([
                        'user_id' => $winner->getKey(),
                        'game_id' => $game->getKey(),
                        'play_id' => $play?->getKey(),
                        'coins' => 0,
                        'diamonds' => 0,
                        'status' => GameReward::STATUS_PENDING_FULFILLMENT,
                        'metadata' => [
                            'rps_reward_id' => (string) $perk->getKey(),
                            'match_id' => (string) $match->getKey(),
                            'reward_type' => $match->reward_type,
                        ],
                    ]);
                }

                RpsRewardGranted::dispatch($match, $perk);
            }

            $this->writeHistory($match, (string) $winner->getKey(), $game, $play?->getKey(), $coinReward, $diamondReward, true, $result);

            $loserId = $match->opponentIdFor((string) $winner->getKey());
            if ($loserId !== null) {
                $loser = User::query()->find($loserId);
                $loserPlay = ($game !== null && $loser !== null) ? GamePlay::query()->create([
                    'user_id' => $loser->getKey(),
                    'game_id' => $game->getKey(),
                    'bet_coins' => $fee,
                    'reward_coins' => 0,
                    'reward_diamonds' => 0,
                    'result' => GamePlay::RESULT_LOST,
                    'outcome' => ['match_id' => (string) $match->getKey(), 'server_verified' => true],
                    'status' => GamePlay::STATUS_COMPLETED,
                    'created_at' => now(),
                ]) : null;

                $this->writeHistory($match, $loserId, $game, $loserPlay?->getKey(), 0, 0, false, $result);
            }
        } catch (\Throwable $e) {
            RpsReward::query()->create([
                'match_id' => $match->getKey(),
                'tournament_id' => $match->tournament_id,
                'user_id' => $winner->getKey(),
                'config_id' => $match->config_id,
                'reward_type' => $match->reward_type,
                'amount' => 0,
                'status' => RpsReward::STATUS_FAILED,
                'metadata' => ['error' => $e->getMessage(), 'refund_required' => true],
            ]);

            $this->refundParticipants($match, 'rps_settlement_failed_refund');
        }

        $match->fill([
            'status' => RpsMatch::STATUS_FINISHED,
            'settled_at' => now(),
        ])->save();
    }

    private function refundParticipants(RpsMatch $match, string $action): void
    {
        $fee = (int) $match->entry_fee_coins;
        if ($fee <= 0) {
            return;
        }

        foreach ($match->participantIds() as $userId) {
            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            $alreadyRefunded = RpsReward::query()
                ->where('match_id', $match->getKey())
                ->where('user_id', $user->getKey())
                ->where('is_refund', true)
                ->exists();
            if ($alreadyRefunded) {
                continue;
            }

            $wallet = $this->wallets->lockForUser($user);
            $wallet->increment('coins_balance', $fee);
            $this->writeWalletTransaction(
                $user,
                $match,
                $fee,
                WalletTransaction::CURRENCY_COINS,
                GamePlay::WALLET_TYPE_GAME_REWARD,
                $action,
            );

            RpsReward::query()->create([
                'match_id' => $match->getKey(),
                'tournament_id' => $match->tournament_id,
                'user_id' => $user->getKey(),
                'config_id' => $match->config_id,
                'reward_type' => 'coins',
                'amount' => $fee,
                'currency' => 'coins',
                'is_refund' => true,
                'status' => RpsReward::STATUS_REFUNDED,
                'metadata' => ['action' => $action],
            ]);
        }
    }

    private function writeHistory(
        RpsMatch $match,
        string $userId,
        ?Game $game,
        ?string $playId,
        int $rewardCoins,
        int $rewardDiamonds,
        bool $isWinner,
        string $result,
    ): void {
        $isChallenger = (string) $match->challenger_id === $userId;
        $roundsWon = $isChallenger ? (int) $match->challenger_score : (int) $match->opponent_score;
        $roundsLost = $isChallenger ? (int) $match->opponent_score : (int) $match->challenger_score;

        RpsHistory::query()->create([
            'user_id' => $userId,
            'match_id' => $match->getKey(),
            'tournament_id' => $match->tournament_id,
            'config_id' => $match->config_id,
            'game_id' => $game?->getKey(),
            'play_id' => $playId,
            'opponent_id' => $match->opponentIdFor($userId),
            'mode' => $match->mode,
            'matchmaking' => $match->matchmaking,
            'result' => $isWinner ? 'won' : $result,
            'rounds_won' => $roundsWon,
            'rounds_lost' => $roundsLost,
            'rounds_drawn' => (int) $match->draw_count,
            'entry_fee_coins' => (int) $match->entry_fee_coins,
            'reward_coins' => $rewardCoins,
            'reward_diamonds' => $rewardDiamonds,
            'is_winner' => $isWinner,
            'snapshot' => [
                'challenger_score' => (int) $match->challenger_score,
                'opponent_score' => (int) $match->opponent_score,
                'rounds_played' => (int) $match->rounds_played,
                'forfeited_by' => $match->forfeited_by,
            ],
        ]);
    }

    private function debit(Wallet $wallet, User $user, RpsMatch $match, int $amount, string $action): void
    {
        $wallet->decrement('coins_balance', $amount);
        $this->writeWalletTransaction(
            $user,
            $match,
            -$amount,
            WalletTransaction::CURRENCY_COINS,
            GamePlay::WALLET_TYPE_GAME_BET,
            $action,
        );
    }

    private function writeWalletTransaction(
        User $user,
        RpsMatch $match,
        int $amount,
        string $currency,
        string $type,
        string $action,
    ): void {
        WalletTransaction::query()->create([
            'user_id' => $user->getKey(),
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'reference_type' => $match->getMorphClass(),
            'reference_id' => $match->getKey(),
            'metadata' => [
                'game_slug' => Game::SLUG_ROCK_PAPER_SCISSORS,
                'action' => $action,
                'match_id' => (string) $match->getKey(),
            ],
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $entry */
    private function appendAudit(RpsMatch $match, array $entry): void
    {
        $log = $match->audit_log ?? [];
        $log[] = $entry;
        $match->forceFill(['audit_log' => $log])->save();
    }

    private function emitSettlement(RpsMatch $match): void
    {
        RpsMatchFinished::dispatch($match);

        foreach ($match->participantIds() as $userId) {
            $user = User::query()->find($userId);
            if ($user !== null) {
                WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());
            }
        }
    }

    private function assertCreateAllowed(User $user, RpsConfig $config, CreateRpsMatchDTO $data): void
    {
        if ($config->max_created_per_day !== null) {
            $created = $this->matches->countCreatedToday($user, (string) $config->getKey());
            if ($created >= (int) $config->max_created_per_day) {
                throw ValidationException::withMessages(['limit' => 'Daily challenge limit reached.']);
            }
        }

        if (! in_array($data->mode, $config->modes_enabled ?? RpsConfig::MODES, true)) {
            throw ValidationException::withMessages(['mode' => 'Mode not enabled.']);
        }

        if (! in_array($data->matchmaking, $config->matchmaking_enabled ?? RpsConfig::MATCHMAKING, true)) {
            throw ValidationException::withMessages(['matchmaking' => 'Matchmaking type not enabled.']);
        }

        if (! in_array($data->rewardType, $config->reward_types_enabled ?? RpsConfig::REWARD_TYPES, true)) {
            throw ValidationException::withMessages(['reward_type' => 'Reward type not enabled.']);
        }

        $fee = $data->entryFeeCoins;
        if ($fee < (int) $config->min_entry_fee_coins || $fee > (int) $config->max_entry_fee_coins) {
            throw ValidationException::withMessages(['entry_fee_coins' => 'Invalid entry fee.']);
        }

        if ($data->roundTimeoutSeconds !== null) {
            $timeout = $data->roundTimeoutSeconds;
            if ($timeout < (int) $config->min_round_timeout_seconds || $timeout > (int) $config->max_round_timeout_seconds) {
                throw ValidationException::withMessages(['round_timeout_seconds' => 'Invalid round timeout.']);
            }
        }

        if ($data->mode === RpsMatch::MODE_VIP_ARENA && ! $this->userIsVip($user)) {
            throw ValidationException::withMessages(['mode' => 'VIP arena requires an active VIP subscription.']);
        }

        if (in_array($data->mode, [RpsMatch::MODE_LIVE_ROOM, RpsMatch::MODE_VOICE_ROOM], true)
            && ($data->roomId === null || $data->roomId === '')) {
            throw ValidationException::withMessages(['room_id' => 'Room is required for this mode.']);
        }

        if ($data->mode === RpsMatch::MODE_TOURNAMENT && ($data->tournamentId === null || $data->tournamentId === '')) {
            throw ValidationException::withMessages(['tournament_id' => 'Tournament is required.']);
        }

        if ($data->matchmaking === RpsMatch::MATCHMAKING_PRIVATE && $data->opponentId === null) {
            throw ValidationException::withMessages(['opponent_id' => 'A private challenge needs an opponent.']);
        }

        if ($data->opponentId !== null && (string) $data->opponentId === (string) $user->getKey()) {
            throw ValidationException::withMessages(['opponent_id' => 'You cannot challenge yourself.']);
        }
    }

    private function assertJoinEligibility(User $user, RpsMatch $match): void
    {
        if ($match->eligibility === 'vip' && ! $this->userIsVip($user)) {
            throw ValidationException::withMessages(['eligibility' => 'VIP required.']);
        }

        if ($match->is_vip_arena && ! $this->userIsVip($user)) {
            throw ValidationException::withMessages(['eligibility' => 'VIP arena requires an active VIP subscription.']);
        }

        if ($match->eligibility === 'whitelist') {
            $ids = array_map('strval', $match->whitelist_user_ids ?? []);
            if (! in_array((string) $user->getKey(), $ids, true)) {
                throw ValidationException::withMessages(['eligibility' => 'Not on whitelist.']);
            }
        }
    }

    private function requireActiveConfig(): RpsConfig
    {
        $config = $this->matches->findDefaultConfig();
        if ($config === null || ! $config->isActive()) {
            throw ValidationException::withMessages(['config' => 'Rock Paper Scissors is unavailable.']);
        }

        return $config;
    }

    private function requireGame(): Game
    {
        $game = Game::query()->where('slug', Game::SLUG_ROCK_PAPER_SCISSORS)->first();
        if ($game === null || ! $game->isActive()) {
            throw ValidationException::withMessages(['game' => 'Rock Paper Scissors is unavailable.']);
        }

        return $game;
    }

    private function ensureEligible(User $user): void
    {
        if ($user->status !== User::STATUS_ACTIVE || $user->is_guest) {
            throw new AuthorizationException('Not eligible to play Rock Paper Scissors.');
        }
    }

    private function userIsVip(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $subscription = $this->vips->findForUser($user);

        return $subscription !== null && $subscription->isActive();
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
