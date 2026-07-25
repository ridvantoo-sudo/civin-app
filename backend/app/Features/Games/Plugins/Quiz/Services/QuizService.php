<?php

namespace App\Features\Games\Plugins\Quiz\Services;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\GameReward;
use App\Features\Games\Plugins\Quiz\DTOs\JoinQuizDTO;
use App\Features\Games\Plugins\Quiz\DTOs\QuizDTO;
use App\Features\Games\Plugins\Quiz\DTOs\StartQuizDTO;
use App\Features\Games\Plugins\Quiz\DTOs\SubmitAnswerDTO;
use App\Features\Games\Plugins\Quiz\Events\AnswerSubmitted;
use App\Features\Games\Plugins\Quiz\Events\QuestionEnded;
use App\Features\Games\Plugins\Quiz\Events\QuestionStarted;
use App\Features\Games\Plugins\Quiz\Events\QuizCreated;
use App\Features\Games\Plugins\Quiz\Events\QuizFinished;
use App\Features\Games\Plugins\Quiz\Events\QuizJoined;
use App\Features\Games\Plugins\Quiz\Events\QuizStarted;
use App\Features\Games\Plugins\Quiz\Events\RewardGranted;
use App\Features\Games\Plugins\Quiz\Events\ScoreUpdated;
use App\Features\Games\Plugins\Quiz\Models\QuizAnswer;
use App\Features\Games\Plugins\Quiz\Models\QuizCategory;
use App\Features\Games\Plugins\Quiz\Models\QuizConfig;
use App\Features\Games\Plugins\Quiz\Models\QuizHistory;
use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use App\Features\Games\Plugins\Quiz\Models\QuizReward;
use App\Features\Games\Plugins\Quiz\Models\QuizScore;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Repositories\Contracts\QuizQuestionRepository;
use App\Features\Games\Plugins\Quiz\Repositories\Contracts\QuizRepository;
use App\Features\Games\Plugins\Quiz\Support\QuizScoringEngine;
use App\Features\Games\Repositories\Contracts\GameRepository;
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

final readonly class QuizService
{
    private const CREATE_RATE_KEY = 'quiz-create:%s';

    private const JOIN_RATE_KEY = 'quiz-join:%s';

    private const ANSWER_RATE_KEY = 'quiz-answer:%s';

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private QuizRepository $quizzes,
        private QuizQuestionRepository $questions,
        private WalletRepository $wallets,
        private GameRepository $games,
        private QuizScoringEngine $scoring,
        private QuizTournamentService $tournaments,
        private VipRepository $vips,
    ) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        $this->ensureEligible($user);
        $config = $this->requireActiveConfig();
        $createdToday = $this->quizzes->countCreatedToday($user, $config->getKey());

        return [
            'id' => $config->id,
            'slug' => $config->slug,
            'name' => $config->name,
            'description' => $config->description,
            'version' => $config->version,
            'min_bet_coins' => $config->min_bet_coins,
            'max_bet_coins' => $config->max_bet_coins,
            'default_bet_coins' => $config->default_bet_coins,
            'default_question_count' => $config->default_question_count,
            'max_question_count' => $config->max_question_count,
            'default_time_per_question' => $config->default_time_per_question,
            'min_time_per_question' => $config->min_time_per_question,
            'max_time_per_question' => $config->max_time_per_question,
            'default_winner_count' => $config->default_winner_count,
            'max_winner_count' => $config->max_winner_count,
            'max_players' => $config->max_players,
            'modes_enabled' => $config->modes_enabled ?? QuizConfig::MODES,
            'question_types_enabled' => $config->question_types_enabled ?? QuizConfig::QUESTION_TYPES,
            'difficulties_enabled' => $config->difficulties_enabled ?? QuizConfig::DIFFICULTIES,
            'reward_types_enabled' => $config->reward_types_enabled ?? QuizConfig::REWARD_TYPES,
            'visibilities_enabled' => $config->visibilities_enabled ?? QuizConfig::VISIBILITIES,
            'eligibilities_enabled' => $config->eligibilities_enabled ?? QuizConfig::ELIGIBILITIES,
            'scoring_rules' => $config->scoring_rules ?? QuizConfig::DEFAULT_SCORING,
            'vip_reward_multiplier' => (float) $config->vip_reward_multiplier,
            'max_created_per_day' => $config->max_created_per_day,
            'created_today' => $createdToday,
            'remaining_today' => $config->max_created_per_day === null
                ? null
                : max(0, (int) $config->max_created_per_day - $createdToday),
            'is_vip' => $this->userIsVip($user),
            'status' => $config->status,
            'enabled' => $config->enabled,
            'metadata' => $config->metadata,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function categories(User $user): Collection
    {
        $this->ensureEligible($user);

        return QuizCategory::query()
            ->where('status', QuizCategory::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (QuizCategory $c): array => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name,
                'description' => $c->description,
                'icon' => $c->icon,
                'is_custom' => $c->is_custom,
            ]);
    }

    public function questions(User $user, ?string $categoryId = null, ?string $difficulty = null, ?string $language = null, int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->questions->paginate($categoryId, $difficulty, $language, $perPage);
    }

    /**
     * @return array{session: QuizSession, wallet: Wallet, replay: bool}
     */
    public function create(User $user, QuizDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::CREATE_RATE_KEY, $user->getKey()), 20);
        $this->assertUuid($data->idempotencyKey);

        $existing = $this->quizzes->findSessionByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            return [
                'session' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        $config = $this->requireActiveConfig();
        $this->assertCreateAllowed($user, $config, $data);
        $game = $this->requireGame();

        try {
            $result = DB::transaction(function () use ($user, $data, $config, $game): array {
                $duplicate = QuizSession::query()
                    ->where('host_id', $user->getKey())
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return [
                        'session' => $duplicate->loadMissing(['host', 'category', 'players.user']),
                        'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                        'replay' => true,
                    ];
                }

                $wallet = $this->wallets->lockForUser($user);
                $entryCost = max(0, $data->betCoins);
                // Non-wallet rewards are still funded in coins from the host pool.
                $poolCost = max(0, $data->rewardPool);
                $totalDebit = $entryCost + $poolCost;

                if ($totalDebit > 0 && (int) $wallet->coins_balance < $totalDebit) {
                    throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
                }

                $fundedPool = $poolCost + $entryCost;

                $session = $this->quizzes->createSession([
                    'config_id' => $config->getKey(),
                    'game_id' => $game->getKey(),
                    'host_id' => $user->getKey(),
                    'tournament_id' => $data->tournamentId,
                    'category_id' => $data->categoryId,
                    'idempotency_key' => $data->idempotencyKey,
                    'mode' => $data->mode,
                    'difficulty' => $data->difficulty,
                    'status' => QuizSession::STATUS_LOBBY,
                    'visibility' => $data->visibility,
                    'eligibility' => $data->eligibility,
                    'reward_type' => $data->rewardType,
                    'reward_pool' => $fundedPool,
                    'bet_coins' => $data->betCoins,
                    'question_count' => $data->questionCount,
                    'time_per_question' => $data->mode === QuizSession::MODE_SPEED
                        ? min($data->timePerQuestion, 10)
                        : $data->timePerQuestion,
                    'winner_count' => $data->winnerCount,
                    'current_round' => 0,
                    'current_question_index' => 0,
                    'room_id' => $data->roomId,
                    'room_type' => $data->roomType,
                    'language' => $data->language,
                    'player_count' => 1,
                    'config_version' => $config->version,
                    'question_ids' => $data->questionIds,
                    'scoring_rules' => $config->scoring_rules ?? QuizConfig::DEFAULT_SCORING,
                    'whitelist_user_ids' => $data->whitelistUserIds,
                    'team_settings' => $data->teamSettings === [] ? null : $data->teamSettings,
                    'audit_log' => [['event' => 'created', 'at' => now()->toISOString(), 'user_id' => $user->getKey()]],
                    'metadata' => array_filter([
                        'client_session_id' => $data->clientSessionId,
                        'request_metadata' => $data->metadata === [] ? null : $data->metadata,
                        'funded_with' => 'coins',
                        'non_wallet_reward' => ! in_array($data->rewardType, ['coins', 'diamonds'], true) ?: null,
                    ]),
                ]);

                $this->quizzes->createPlayer([
                    'session_id' => $session->getKey(),
                    'user_id' => $user->getKey(),
                    'idempotency_key' => $data->idempotencyKey,
                    'status' => QuizPlayer::STATUS_JOINED,
                    'is_host' => true,
                    'joined_at' => now(),
                ]);

                if ($totalDebit > 0) {
                    $wallet->decrement('coins_balance', $totalDebit);
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_BET,
                        'amount' => -$totalDebit,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $session->getMorphClass(),
                        'reference_id' => $session->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_QUIZ,
                            'action' => 'quiz_create_bet',
                            'session_id' => $session->getKey(),
                        ],
                        'created_at' => now(),
                    ]);
                }

                return [
                    'session' => $session->fresh()->loadMissing(['host', 'category', 'players.user']),
                    'wallet' => $wallet->fresh(),
                    'replay' => false,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->quizzes->findSessionByIdempotency($user, $data->idempotencyKey);
            if ($existing === null) {
                throw ValidationException::withMessages(['idempotency_key' => 'Duplicate request.']);
            }

            return [
                'session' => $existing,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        if (! $result['replay']) {
            QuizCreated::dispatch($result['session']);
            WalletUpdated::dispatch($result['wallet']);
        }

        return $result;
    }

    /**
     * @return array{session: QuizSession, player: QuizPlayer, wallet: Wallet, replay: bool}
     */
    public function join(User $user, JoinQuizDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::JOIN_RATE_KEY, $user->getKey()), 60);
        $this->assertUuid($data->idempotencyKey);

        $existingPlayer = $this->quizzes->findPlayerByIdempotency($user, $data->idempotencyKey);
        if ($existingPlayer !== null) {
            return [
                'session' => $this->quizzes->findSession($existingPlayer->session_id) ?? $existingPlayer->session,
                'player' => $existingPlayer,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        $config = $this->requireActiveConfig();

        try {
            $result = DB::transaction(function () use ($user, $data, $config): array {
                $session = QuizSession::query()->whereKey($data->sessionId)->lockForUpdate()->first();
                if ($session === null) {
                    throw ValidationException::withMessages(['session_id' => 'Quiz session not found.']);
                }
                if (! $session->isJoinable()) {
                    throw ValidationException::withMessages(['session_id' => 'Quiz is not joinable.']);
                }

                $already = QuizPlayer::query()
                    ->where('session_id', $session->getKey())
                    ->where('user_id', $user->getKey())
                    ->first();
                if ($already !== null) {
                    if ((string) $already->idempotency_key === (string) $data->idempotencyKey) {
                        return [
                            'session' => $session->fresh()->loadMissing(['host', 'category', 'players.user']),
                            'player' => $already,
                            'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                            'replay' => true,
                        ];
                    }

                    throw ValidationException::withMessages(['session_id' => 'Already joined this quiz.']);
                }

                $this->assertJoinEligibility($user, $session);

                if ((int) $session->player_count >= (int) $config->max_players) {
                    throw ValidationException::withMessages(['session_id' => 'Quiz is full.']);
                }

                $wallet = $this->wallets->lockForUser($user);
                $bet = (int) $session->bet_coins;
                if ($bet > 0 && (int) $wallet->coins_balance < $bet) {
                    throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
                }

                $player = $this->quizzes->createPlayer([
                    'session_id' => $session->getKey(),
                    'user_id' => $user->getKey(),
                    'idempotency_key' => $data->idempotencyKey,
                    'status' => QuizPlayer::STATUS_JOINED,
                    'team_id' => $data->teamId,
                    'team_name' => $data->teamName,
                    'is_host' => false,
                    'joined_at' => now(),
                    'metadata' => $data->metadata === [] ? null : $data->metadata,
                ]);

                if ($bet > 0) {
                    $wallet->decrement('coins_balance', $bet);
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_BET,
                        'amount' => -$bet,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $session->getMorphClass(),
                        'reference_id' => $session->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_QUIZ,
                            'action' => 'quiz_join_bet',
                            'session_id' => $session->getKey(),
                        ],
                        'created_at' => now(),
                    ]);
                    $session->increment('reward_pool', $bet);
                }

                $session->increment('player_count');

                return [
                    'session' => $session->fresh()->loadMissing(['host', 'category', 'players.user']),
                    'player' => $player,
                    'wallet' => $wallet->fresh(),
                    'replay' => false,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $existingPlayer = $this->quizzes->findPlayerByIdempotency($user, $data->idempotencyKey);
            if ($existingPlayer === null) {
                throw ValidationException::withMessages(['idempotency_key' => 'Duplicate request.']);
            }

            return [
                'session' => $this->quizzes->findSession($existingPlayer->session_id) ?? $existingPlayer->session,
                'player' => $existingPlayer,
                'wallet' => $this->wallets->findOrCreateForUser($user)->fresh(),
                'replay' => true,
            ];
        }

        if (! $result['replay']) {
            QuizJoined::dispatch($result['session'], $result['player']);
            WalletUpdated::dispatch($result['wallet']);
        }

        return $result;
    }

    /**
     * @return array{session: QuizSession, question: array<string, mixed>|null, replay: bool}
     */
    public function start(User $user, StartQuizDTO $data): array
    {
        $this->ensureEligible($user);
        $this->assertUuid($data->idempotencyKey);

        $result = DB::transaction(function () use ($user, $data): array {
            $session = QuizSession::query()->whereKey($data->sessionId)->lockForUpdate()->first();
            if ($session === null) {
                throw ValidationException::withMessages(['session_id' => 'Quiz session not found.']);
            }
            if ((string) $session->host_id !== (string) $user->getKey()) {
                throw new AuthorizationException('Only the host can start the quiz.');
            }
            if ($session->status !== QuizSession::STATUS_LOBBY) {
                return [
                    'session' => $session->fresh()->loadMissing(['host', 'category', 'players.user']),
                    'question' => $this->currentPublicQuestion($session),
                    'replay' => true,
                ];
            }

            $questionIds = $session->question_ids;
            if (! is_array($questionIds) || $questionIds === []) {
                $picked = $this->questions->pickQuestions(
                    (int) $session->question_count,
                    $session->category_id,
                    $session->difficulty,
                    $session->language,
                );
                if ($picked->isEmpty()) {
                    throw ValidationException::withMessages(['questions' => 'No playable questions available.']);
                }
                $questionIds = $picked->pluck('id')->values()->all();
            }

            $firstId = (string) $questionIds[0];
            $first = $this->questions->findPlayable($firstId);
            if ($first === null) {
                throw ValidationException::withMessages(['questions' => 'First question is unavailable.']);
            }

            $now = now();
            $session->fill([
                'status' => QuizSession::STATUS_QUESTION,
                'question_ids' => $questionIds,
                'question_count' => count($questionIds),
                'current_question_index' => 0,
                'current_question_id' => $firstId,
                'question_started_at' => $now,
                'question_ends_at' => $now->copy()->addSeconds((int) $session->time_per_question),
                'started_at' => $now,
                'current_round' => 1,
            ])->save();

            QuizPlayer::query()
                ->where('session_id', $session->getKey())
                ->update(['status' => QuizPlayer::STATUS_PLAYING]);

            return [
                'session' => $session->fresh()->loadMissing(['host', 'category', 'players.user']),
                'question' => $first->publicPayload(),
                'replay' => false,
            ];
        });

        if (! $result['replay']) {
            QuizStarted::dispatch($result['session']);
            QuestionStarted::dispatch($result['session'], $result['question']);
        }

        return $result;
    }

    /**
     * @return array{session: QuizSession, answer: QuizAnswer, player: QuizPlayer, score: array<string, mixed>, replay: bool}
     */
    public function answer(User $user, SubmitAnswerDTO $data): array
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::ANSWER_RATE_KEY, $user->getKey()), 120);
        $this->assertUuid($data->idempotencyKey);

        $existing = $this->quizzes->findAnswerByIdempotency($user, $data->idempotencyKey);
        if ($existing !== null) {
            $session = $this->quizzes->findSession($existing->session_id);
            $player = QuizPlayer::query()->find($existing->player_id);

            return [
                'session' => $session ?? $existing->session,
                'answer' => $existing,
                'player' => $player ?? $existing->player,
                'score' => [
                    'points_awarded' => (int) $existing->points_awarded,
                    'total_score' => (int) ($player?->score ?? 0),
                    'streak' => (int) ($player?->streak ?? 0),
                    'is_correct' => (bool) $existing->is_correct,
                ],
                'replay' => true,
            ];
        }

        try {
            $result = DB::transaction(function () use ($user, $data): array {
                $session = QuizSession::query()->whereKey($data->sessionId)->lockForUpdate()->first();
                if ($session === null) {
                    throw ValidationException::withMessages(['session_id' => 'Quiz session not found.']);
                }
                if ($session->status !== QuizSession::STATUS_QUESTION) {
                    throw ValidationException::withMessages(['session_id' => 'No active question.']);
                }
                if ((string) $session->current_question_id !== (string) $data->questionId) {
                    throw ValidationException::withMessages(['question_id' => 'Question is not active.']);
                }

                $now = now();
                if ($session->question_ends_at !== null && $now->greaterThan($session->question_ends_at)) {
                    throw ValidationException::withMessages(['question_id' => 'Question timed out.']);
                }

                $player = QuizPlayer::query()
                    ->where('session_id', $session->getKey())
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first();
                if ($player === null) {
                    throw ValidationException::withMessages(['session_id' => 'You are not in this quiz.']);
                }

                $duplicate = QuizAnswer::query()
                    ->where('session_id', $session->getKey())
                    ->where('user_id', $user->getKey())
                    ->where('question_id', $data->questionId)
                    ->first();
                if ($duplicate !== null) {
                    throw ValidationException::withMessages(['question_id' => 'Already answered.']);
                }

                $question = $this->questions->findPlayable($data->questionId);
                if ($question === null) {
                    throw ValidationException::withMessages(['question_id' => 'Question not found.']);
                }

                $responseMs = $data->clientResponseMs;
                if ($session->question_started_at !== null) {
                    $serverMs = (int) $session->question_started_at->diffInMilliseconds($now);
                    $responseMs = $responseMs === null ? $serverMs : min($serverMs, max(0, $responseMs));
                }

                $scored = $this->scoring->scoreAnswer(
                    $session,
                    $player,
                    $question,
                    $data->selectedIndex,
                    $data->selectedText,
                    $responseMs,
                    false,
                );

                $answer = $this->quizzes->createAnswer([
                    'session_id' => $session->getKey(),
                    'player_id' => $player->getKey(),
                    'user_id' => $user->getKey(),
                    'question_id' => $question->getKey(),
                    'idempotency_key' => $data->idempotencyKey,
                    'round_index' => (int) $session->current_round,
                    'question_index' => (int) $session->current_question_index,
                    'selected_index' => $data->selectedIndex,
                    'selected_text' => $data->selectedText,
                    'is_correct' => $scored['is_correct'],
                    'points_awarded' => $scored['points'],
                    'speed_bonus' => $scored['speed_bonus'],
                    'streak_bonus' => $scored['streak_bonus'],
                    'response_ms' => $responseMs,
                    'timed_out' => false,
                    'server_seed' => Str::random(32),
                    'audit' => [
                        'correct_index_checked_server_side' => true,
                        'tamper_check' => 'passed',
                    ],
                    'metadata' => $data->metadata === [] ? null : $data->metadata,
                    'answered_at' => $now,
                ]);

                $player->score = (int) $player->score + $scored['points'];
                $player->answers_count = (int) $player->answers_count + 1;
                if ($scored['is_correct']) {
                    $player->correct_count = (int) $player->correct_count + 1;
                    $player->streak = $scored['streak'];
                    $player->best_streak = max((int) $player->best_streak, $scored['streak']);
                } else {
                    $player->wrong_count = (int) $player->wrong_count + 1;
                    $player->streak = 0;
                }
                if ($responseMs !== null) {
                    $player->total_response_ms = (int) $player->total_response_ms + $responseMs;
                }
                $player->save();

                $finished = false;
                $wallet = null;
                $advance = $this->maybeAdvanceOrFinish($session);
                if ($advance['finished']) {
                    $this->settleSessionInternal($session->fresh());
                    $finished = true;
                    $wallet = $this->wallets->findOrCreateForUser($user)->fresh();
                }

                $player = $player->fresh();

                return [
                    'session' => $session->fresh()->loadMissing(['host', 'category', 'players.user', 'scores', 'rewards']),
                    'answer' => $answer,
                    'player' => $player,
                    'score' => [
                        'points_awarded' => $scored['points'],
                        'speed_bonus' => $scored['speed_bonus'],
                        'streak_bonus' => $scored['streak_bonus'],
                        'total_score' => (int) $player->score,
                        'streak' => (int) $player->streak,
                        'is_correct' => $scored['is_correct'],
                    ],
                    'wallet' => $wallet,
                    'finished' => $finished,
                    'replay' => false,
                    'advanced' => $advance,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->quizzes->findAnswerByIdempotency($user, $data->idempotencyKey);
            if ($existing === null) {
                throw ValidationException::withMessages(['idempotency_key' => 'Duplicate request.']);
            }
            $session = $this->quizzes->findSession($existing->session_id);
            $player = QuizPlayer::query()->find($existing->player_id);

            return [
                'session' => $session ?? $existing->session,
                'answer' => $existing,
                'player' => $player ?? $existing->player,
                'score' => [
                    'points_awarded' => (int) $existing->points_awarded,
                    'total_score' => (int) ($player?->score ?? 0),
                    'streak' => (int) ($player?->streak ?? 0),
                    'is_correct' => (bool) $existing->is_correct,
                ],
                'replay' => true,
            ];
        }

        if (! $result['replay']) {
            AnswerSubmitted::dispatch($result['session'], $result['answer']);
            ScoreUpdated::dispatch($result['session'], $result['player']);
            if (($result['advanced']['question_ended'] ?? false) === true) {
                QuestionEnded::dispatch($result['session'], $result['advanced']['ended_question'] ?? null);
            }
            if (($result['advanced']['question'] ?? null) !== null) {
                QuestionStarted::dispatch($result['session'], $result['advanced']['question']);
            }
            if ($result['finished']) {
                QuizFinished::dispatch($result['session']);
            }
            if ($result['wallet'] !== null) {
                WalletUpdated::dispatch($result['wallet']);
            }
        }

        unset($result['advanced'], $result['finished'], $result['wallet']);

        return $result;
    }

    /**
     * Advance timed-out questions / settle sessions. Used by the scheduler.
     *
     * @return array{advanced: int, settled: int, tournaments_started: int, tournaments_finished: int}
     */
    public function processDueSessions(int $limit = 100): array
    {
        $advanced = 0;
        $settled = 0;

        $due = QuizSession::query()
            ->where('status', QuizSession::STATUS_QUESTION)
            ->whereNotNull('question_ends_at')
            ->where('question_ends_at', '<=', now())
            ->orderBy('question_ends_at')
            ->limit($limit)
            ->get();

        foreach ($due as $session) {
            $outcome = DB::transaction(function () use ($session): ?array {
                $locked = QuizSession::query()->whereKey($session->getKey())->lockForUpdate()->first();
                if ($locked === null || $locked->status !== QuizSession::STATUS_QUESTION) {
                    return null;
                }
                if ($locked->question_ends_at === null || $locked->question_ends_at->isFuture()) {
                    return null;
                }

                $advance = $this->maybeAdvanceOrFinish($locked);
                if ($advance['finished']) {
                    $this->settleSessionInternal($locked->fresh());
                }

                return $advance;
            });

            if ($outcome === null) {
                continue;
            }

            $fresh = QuizSession::query()->with(['players'])->find($session->getKey());
            if ($fresh === null) {
                continue;
            }

            if ($outcome['question_ended']) {
                QuestionEnded::dispatch($fresh, $outcome['ended_question'] ?? null);
                $advanced++;
            }
            if (($outcome['question'] ?? null) !== null) {
                QuestionStarted::dispatch($fresh, $outcome['question']);
            }
            if ($outcome['finished']) {
                QuizFinished::dispatch($fresh);
                $settled++;
            }
        }

        $tournaments = $this->tournaments->processDue($limit);

        return [
            'advanced' => $advanced,
            'settled' => $settled,
            'tournaments_started' => $tournaments['started'],
            'tournaments_finished' => $tournaments['finished'],
        ];
    }

    public function settleSession(string $sessionId): QuizSession
    {
        return DB::transaction(function () use ($sessionId): QuizSession {
            $session = QuizSession::query()->whereKey($sessionId)->lockForUpdate()->first();
            if ($session === null) {
                throw ValidationException::withMessages(['session_id' => 'Quiz session not found.']);
            }

            $this->settleSessionInternal($session);

            return $session->fresh()->loadMissing(['host', 'category', 'players.user', 'scores', 'rewards']);
        });
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->quizzes->history($user, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50, ?string $categoryId = null, ?string $roomId = null): Collection
    {
        return $this->quizzes->leaderboard($period, $limit, $categoryId, $roomId);
    }

    /** @return array<string, mixed> */
    public function statistics(User $user): array
    {
        $this->ensureEligible($user);

        return $this->quizzes->statistics($user);
    }

    public function tournaments(User $user, int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->tournaments->list($perPage);
    }

    public function show(User $user, string $sessionId): QuizSession
    {
        $this->ensureEligible($user);
        $session = $this->quizzes->findSession($sessionId);
        if ($session === null) {
            throw ValidationException::withMessages(['session_id' => 'Quiz session not found.']);
        }

        return $session;
    }

    /**
     * Advance when all players answered or time expired for current question.
     *
     * @return array{finished: bool, question_ended: bool, question: array<string, mixed>|null, ended_question: array<string, mixed>|null}
     */
    private function maybeAdvanceOrFinish(QuizSession $session): array
    {
        $playerCount = QuizPlayer::query()
            ->where('session_id', $session->getKey())
            ->whereIn('status', [QuizPlayer::STATUS_JOINED, QuizPlayer::STATUS_PLAYING, QuizPlayer::STATUS_FINISHED])
            ->count();
        $answerCount = QuizAnswer::query()
            ->where('session_id', $session->getKey())
            ->where('question_id', $session->current_question_id)
            ->count();

        $timeExpired = $session->question_ends_at !== null && now()->greaterThanOrEqualTo($session->question_ends_at);
        if ($answerCount < $playerCount && ! $timeExpired) {
            return ['finished' => false, 'question_ended' => false, 'question' => null, 'ended_question' => null];
        }

        $endedQuestion = null;
        if ($session->current_question_id !== null) {
            $ended = $this->questions->findPlayable((string) $session->current_question_id);
            if ($ended !== null) {
                $endedQuestion = array_merge($ended->publicPayload(), [
                    'correct_index' => (int) $ended->correct_index,
                ]);
            }
        }

        $ids = $session->question_ids ?? [];
        $nextIndex = (int) $session->current_question_index + 1;

        if ($nextIndex >= count($ids)) {
            $session->fill([
                'status' => QuizSession::STATUS_SETTLING,
                'question_ends_at' => now(),
            ])->save();

            return ['finished' => true, 'question_ended' => true, 'question' => null, 'ended_question' => $endedQuestion];
        }

        $nextId = (string) $ids[$nextIndex];
        $next = $this->questions->findPlayable($nextId);
        $now = now();
        $session->fill([
            'status' => QuizSession::STATUS_QUESTION,
            'current_question_index' => $nextIndex,
            'current_question_id' => $nextId,
            'question_started_at' => $now,
            'question_ends_at' => $now->copy()->addSeconds((int) $session->time_per_question),
            'current_round' => (int) $session->current_round + 1,
        ])->save();

        return [
            'finished' => false,
            'question_ended' => true,
            'question' => $next?->publicPayload(),
            'ended_question' => $endedQuestion,
        ];
    }

    private function settleSessionInternal(QuizSession $session): ?Wallet
    {
        $session = QuizSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
        if ($session->status === QuizSession::STATUS_FINISHED) {
            return null;
        }

        $config = $this->requireActiveConfig();
        $rules = $session->scoring_rules ?? QuizConfig::DEFAULT_SCORING;
        $players = QuizPlayer::query()
            ->where('session_id', $session->getKey())
            ->orderByDesc('score')
            ->orderBy('total_response_ms')
            ->get();

        $winnerCount = min((int) $session->winner_count, $players->count());
        $lastWallet = null;
        $game = Game::query()->where('slug', Game::SLUG_QUIZ)->first();

        foreach ($players as $index => $player) {
            $rank = $index + 1;
            $isWinner = $rank <= $winnerCount;
            $vipBonus = 0;
            $tournamentBonus = 0;
            $perfectBonus = 0;

            if ($player->correct_count > 0 && $player->wrong_count === 0 && $player->answers_count >= (int) $session->question_count) {
                $perfectBonus = (int) ($rules['perfect_round_bonus'] ?? 200);
            }
            if ($session->mode === QuizSession::MODE_TOURNAMENT) {
                $tournamentBonus = (int) ($rules['tournament_bonus'] ?? 150);
            }
            if ($this->userIsVip(User::query()->find($player->user_id))) {
                $vipBonus = (int) round(((int) $player->score) * ((float) ($rules['vip_bonus_percent'] ?? 10) / 100));
            }

            $total = (int) $player->score + $perfectBonus + $tournamentBonus + $vipBonus;

            $score = QuizScore::query()->updateOrCreate(
                ['session_id' => $session->getKey(), 'user_id' => $player->user_id],
                [
                    'player_id' => $player->getKey(),
                    'team_id' => $player->team_id,
                    'total_score' => $total,
                    'correct_count' => $player->correct_count,
                    'wrong_count' => $player->wrong_count,
                    'speed_bonus_total' => QuizAnswer::query()->where('player_id', $player->getKey())->sum('speed_bonus'),
                    'streak_bonus_total' => QuizAnswer::query()->where('player_id', $player->getKey())->sum('streak_bonus'),
                    'perfect_round_bonus' => $perfectBonus,
                    'tournament_bonus' => $tournamentBonus,
                    'vip_bonus' => $vipBonus,
                    'rank' => $rank,
                    'is_winner' => $isWinner,
                    'breakdown' => [
                        'base_score' => (int) $player->score,
                        'perfect_round_bonus' => $perfectBonus,
                        'tournament_bonus' => $tournamentBonus,
                        'vip_bonus' => $vipBonus,
                    ],
                ],
            );

            $player->fill([
                'score' => $total,
                'rank' => $rank,
                'is_winner' => $isWinner,
                'status' => QuizPlayer::STATUS_FINISHED,
            ])->save();

            $rewardCoins = 0;
            $rewardDiamonds = 0;
            $nonWalletAmount = 0;
            if ($isWinner && (int) $session->reward_pool > 0) {
                $share = (int) floor(((int) $session->reward_pool) / max(1, $winnerCount));
                if ($rank === 1 && ((int) $session->reward_pool % max(1, $winnerCount)) > 0) {
                    $share += ((int) $session->reward_pool) % max(1, $winnerCount);
                }
                if ($session->reward_type === 'diamonds') {
                    $rewardDiamonds = $share;
                } elseif ($session->reward_type === 'coins') {
                    $rewardCoins = $share;
                    if ($this->userIsVip(User::query()->find($player->user_id))) {
                        $rewardCoins = (int) round($rewardCoins * (float) $config->vip_reward_multiplier);
                    }
                } else {
                    $nonWalletAmount = $share;
                }
            }

            $user = User::query()->find($player->user_id);
            if ($user === null) {
                continue;
            }

            $wallet = $this->wallets->lockForUser($user);
            $play = null;
            if ($game !== null) {
                $play = GamePlay::query()->create([
                    'user_id' => $user->getKey(),
                    'game_id' => $game->getKey(),
                    'bet_coins' => (int) $session->bet_coins,
                    'reward_coins' => $rewardCoins,
                    'reward_diamonds' => $rewardDiamonds,
                    'result' => $isWinner ? GamePlay::RESULT_WON : GamePlay::RESULT_LOST,
                    'outcome' => [
                        'session_id' => $session->getKey(),
                        'rank' => $rank,
                        'score' => $total,
                        'server_verified' => true,
                    ],
                    'status' => GamePlay::STATUS_COMPLETED,
                    'created_at' => now(),
                ]);
            }

            try {
                if ($rewardCoins > 0) {
                    $wallet->increment('coins_balance', $rewardCoins);
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                        'amount' => $rewardCoins,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $session->getMorphClass(),
                        'reference_id' => $session->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_QUIZ,
                            'action' => 'quiz_reward',
                            'rank' => $rank,
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
                        'reference_type' => $session->getMorphClass(),
                        'reference_id' => $session->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_QUIZ,
                            'action' => 'quiz_reward_diamonds',
                            'rank' => $rank,
                        ],
                        'created_at' => now(),
                    ]);
                }

                $rewardStatus = QuizReward::STATUS_GRANTED;
                if (! in_array($session->reward_type, ['coins', 'diamonds'], true) && $isWinner) {
                    $rewardStatus = QuizReward::STATUS_PENDING;
                }

                $quizReward = QuizReward::query()->create([
                    'session_id' => $session->getKey(),
                    'user_id' => $user->getKey(),
                    'player_id' => $player->getKey(),
                    'score_id' => $score->getKey(),
                    'config_id' => $session->config_id,
                    'reward_type' => $session->reward_type,
                    'amount' => $rewardCoins + $rewardDiamonds + $nonWalletAmount,
                    'currency' => $session->reward_type === 'diamonds' ? 'diamonds' : 'coins',
                    'rank' => $rank,
                    'is_jackpot' => $session->reward_type === 'jackpot',
                    'is_mystery' => $session->reward_type === 'mystery',
                    'is_vip' => $vipBonus > 0,
                    'status' => $isWinner ? $rewardStatus : QuizReward::STATUS_GRANTED,
                    'metadata' => ['non_wallet' => ! in_array($session->reward_type, ['coins', 'diamonds'], true)],
                ]);

                if ($game !== null && ($rewardCoins > 0 || $rewardDiamonds > 0 || ($isWinner && $nonWalletAmount > 0))) {
                    GameReward::query()->create([
                        'user_id' => $user->getKey(),
                        'game_id' => $game->getKey(),
                        'play_id' => $play?->getKey(),
                        'coins' => $rewardCoins,
                        'diamonds' => $rewardDiamonds,
                        'status' => $rewardStatus === QuizReward::STATUS_PENDING
                            ? GameReward::STATUS_PENDING_FULFILLMENT
                            : GameReward::STATUS_GRANTED,
                        'metadata' => [
                            'quiz_reward_id' => $quizReward->getKey(),
                            'session_id' => $session->getKey(),
                            'non_wallet_amount' => $nonWalletAmount,
                        ],
                    ]);
                }

                if ($isWinner) {
                    RewardGranted::dispatch($session, $quizReward);
                }
            } catch (\Throwable $e) {
                QuizReward::query()->create([
                    'session_id' => $session->getKey(),
                    'user_id' => $user->getKey(),
                    'player_id' => $player->getKey(),
                    'score_id' => $score->getKey(),
                    'config_id' => $session->config_id,
                    'reward_type' => $session->reward_type,
                    'amount' => 0,
                    'status' => QuizReward::STATUS_FAILED,
                    'metadata' => ['error' => $e->getMessage(), 'refund_required' => true],
                ]);
                // Refund entry bet on failed settlement for this player.
                if ((int) $session->bet_coins > 0) {
                    $wallet->increment('coins_balance', (int) $session->bet_coins);
                    WalletTransaction::query()->create([
                        'user_id' => $user->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                        'amount' => (int) $session->bet_coins,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $session->getMorphClass(),
                        'reference_id' => $session->getKey(),
                        'metadata' => [
                            'game_slug' => Game::SLUG_QUIZ,
                            'action' => 'quiz_reward_refund',
                        ],
                        'created_at' => now(),
                    ]);
                }
            }

            QuizHistory::query()->create([
                'user_id' => $user->getKey(),
                'session_id' => $session->getKey(),
                'config_id' => $session->config_id,
                'game_id' => $game?->getKey(),
                'play_id' => $play?->getKey(),
                'mode' => $session->mode,
                'result' => $isWinner ? 'won' : 'finished',
                'score' => $total,
                'rank' => $rank,
                'bet_coins' => (int) $session->bet_coins,
                'reward_coins' => $rewardCoins,
                'reward_diamonds' => $rewardDiamonds,
                'is_winner' => $isWinner,
                'snapshot' => [
                    'correct_count' => $player->correct_count,
                    'wrong_count' => $player->wrong_count,
                    'accuracy' => $player->answers_count > 0
                        ? round(($player->correct_count / $player->answers_count) * 100, 2)
                        : 0,
                ],
            ]);

            $lastWallet = $wallet->fresh();
        }

        $session->fill([
            'status' => QuizSession::STATUS_FINISHED,
            'finished_at' => now(),
        ])->save();

        return $lastWallet;
    }

    /** @return array<string, mixed>|null */
    private function currentPublicQuestion(QuizSession $session): ?array
    {
        if ($session->current_question_id === null) {
            return null;
        }
        $q = $this->questions->findPlayable((string) $session->current_question_id);

        return $q?->publicPayload();
    }

    private function assertCreateAllowed(User $user, QuizConfig $config, QuizDTO $data): void
    {
        if ($config->max_created_per_day !== null) {
            $created = $this->quizzes->countCreatedToday($user, $config->getKey());
            if ($created >= (int) $config->max_created_per_day) {
                throw ValidationException::withMessages(['limit' => 'Daily create limit reached.']);
            }
        }

        $modes = $config->modes_enabled ?? QuizConfig::MODES;
        if (! in_array($data->mode, $modes, true)) {
            throw ValidationException::withMessages(['mode' => 'Mode not enabled.']);
        }

        $difficulties = $config->difficulties_enabled ?? QuizConfig::DIFFICULTIES;
        if (! in_array($data->difficulty, $difficulties, true)) {
            throw ValidationException::withMessages(['difficulty' => 'Difficulty not enabled.']);
        }

        $rewards = $config->reward_types_enabled ?? QuizConfig::REWARD_TYPES;
        if (! in_array($data->rewardType, $rewards, true)) {
            throw ValidationException::withMessages(['reward_type' => 'Reward type not enabled.']);
        }

        if ($data->questionCount < 1 || $data->questionCount > (int) $config->max_question_count) {
            throw ValidationException::withMessages(['question_count' => 'Invalid question count.']);
        }
        if ($data->timePerQuestion < (int) $config->min_time_per_question || $data->timePerQuestion > (int) $config->max_time_per_question) {
            throw ValidationException::withMessages(['time_per_question' => 'Invalid time per question.']);
        }
        if ($data->winnerCount < 1 || $data->winnerCount > (int) $config->max_winner_count) {
            throw ValidationException::withMessages(['winner_count' => 'Invalid winner count.']);
        }
        if ($data->betCoins < 0 || $data->betCoins > (int) $config->max_bet_coins) {
            throw ValidationException::withMessages(['bet_coins' => 'Invalid bet.']);
        }
        if (in_array($data->mode, [QuizSession::MODE_LIVE_HOST, QuizSession::MODE_VOICE_ROOM], true) && ($data->roomId === null || $data->roomId === '')) {
            throw ValidationException::withMessages(['room_id' => 'Room is required for this mode.']);
        }
        if ($data->mode === QuizSession::MODE_TOURNAMENT && ($data->tournamentId === null || $data->tournamentId === '')) {
            throw ValidationException::withMessages(['tournament_id' => 'Tournament is required.']);
        }
    }

    private function assertJoinEligibility(User $user, QuizSession $session): void
    {
        if ($session->eligibility === 'vip' && ! $this->userIsVip($user)) {
            throw ValidationException::withMessages(['eligibility' => 'VIP required.']);
        }
        if ($session->eligibility === 'whitelist') {
            $ids = $session->whitelist_user_ids ?? [];
            if (! in_array((string) $user->getKey(), array_map('strval', $ids), true)) {
                throw ValidationException::withMessages(['eligibility' => 'Not on whitelist.']);
            }
        }
    }

    private function requireActiveConfig(): QuizConfig
    {
        $config = $this->quizzes->findDefaultConfig();
        if ($config === null || ! $config->isActive()) {
            throw ValidationException::withMessages(['config' => 'Quiz is unavailable.']);
        }

        return $config;
    }

    private function requireGame(): Game
    {
        $game = Game::query()->where('slug', Game::SLUG_QUIZ)->first();
        if ($game === null || ! $game->isActive()) {
            throw ValidationException::withMessages(['game' => 'Quiz game is unavailable.']);
        }

        return $game;
    }

    private function ensureEligible(User $user): void
    {
        if ($user->status !== User::STATUS_ACTIVE || $user->is_guest) {
            throw new AuthorizationException('Not eligible to play Quiz.');
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
