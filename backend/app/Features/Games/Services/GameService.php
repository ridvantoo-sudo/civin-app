<?php

namespace App\Features\Games\Services;

use App\Features\Games\Core\GameManager;
use App\Features\Games\DTOs\CreateMatchData;
use App\Features\Games\DTOs\MatchActionData;
use App\Features\Games\DTOs\PlayGameData;
use App\Features\Games\DTOs\PlayGameResult;
use App\Features\Games\DTOs\TrackAnalyticsData;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Events\GameMatchUpdated;
use App\Features\Games\Events\GamePlayCompleted;
use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameMatch;
use App\Features\Games\Models\GameMatchPlayer;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\LotteryRound;
use App\Features\Games\Models\LotteryTicket;
use App\Features\Games\Models\QuizQuestion;
use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Models\RedEnvelopeClaim;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class GameService
{
    private const PLAY_RATE_KEY = 'game-play:%s';

    private const RATE_MAX = 60;

    private const RATE_DECAY_SECONDS = 60;

    private const LUDO_FINISH = 50;

    private const RPS_CHOICES = ['rock', 'paper', 'scissors'];

    public function __construct(
        private GameRepository $games,
        private WalletRepository $wallets,
        private GameManager $gameManager,
        private GameRng $rng,
    ) {}

    /** @return Collection<int, Game> */
    public function catalog(): Collection
    {
        return $this->games->catalog();
    }

    public function show(string $slug): Game
    {
        $game = $this->games->findActiveBySlug($slug);
        if ($game === null) {
            throw ValidationException::withMessages(['slug' => 'Game not found.']);
        }

        return $game;
    }

    public function play(User $user, PlayGameData $data): PlayGameResult
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::PLAY_RATE_KEY, $user->getKey()));

        $game = $this->games->findActiveBySlug($data->gameSlug);
        if ($game === null) {
            throw ValidationException::withMessages(['game_slug' => 'Game not found or inactive.']);
        }

        return match ($game->slug) {
            Game::SLUG_RED_ENVELOPE => $this->playRedEnvelope($user, $game, $data),
            Game::SLUG_LOTTERY => $this->playLottery($user, $game, $data),
            Game::SLUG_LUDO => throw ValidationException::withMessages([
                'game_slug' => 'Ludo requires multiplayer match endpoints.',
            ]),
            default => $this->playWithEngine($user, $game, $data),
        };
    }

    public function history(User $user, ?string $gameSlug = null, int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->games->history($user, $gameSlug, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(?string $gameSlug = null, string $period = 'daily', int $limit = 50): Collection
    {
        $period = in_array($period, ['daily', 'weekly', 'all'], true) ? $period : 'daily';

        return $this->games->leaderboard($gameSlug, $period, $limit);
    }

    /** @return array<string, mixed> */
    public function stats(User $user, ?string $gameSlug = null): array
    {
        $this->ensureEligible($user);

        return $this->games->userStats($user, $gameSlug);
    }

    public function trackAnalytics(User $user, TrackAnalyticsData $data): void
    {
        $this->ensureEligible($user);

        $game = null;
        if ($data->gameSlug !== null && $data->gameSlug !== '') {
            $game = $this->games->findBySlug($data->gameSlug);
        }

        $this->games->trackAnalytics($user, $game, $data->event, $data->payload);
    }

    /** @return array<string, mixed> */
    public function quizQuestion(): array
    {
        $question = QuizQuestion::query()
            ->where('status', QuizQuestion::STATUS_ACTIVE)
            ->inRandomOrder()
            ->first();

        if ($question === null) {
            throw ValidationException::withMessages(['quiz' => 'No quiz questions available.']);
        }

        return [
            'id' => $question->id,
            'question' => $question->question,
            'options' => $question->options,
            'difficulty' => $question->difficulty,
            'bet_coins' => $question->bet_coins,
            'reward_coins' => $question->reward_coins,
        ];
    }

    public function currentLotteryRound(?Game $game = null): LotteryRound
    {
        $game ??= $this->games->findActiveBySlug(Game::SLUG_LOTTERY);
        if ($game === null) {
            throw ValidationException::withMessages(['lottery' => 'Lottery game is unavailable.']);
        }

        return $this->ensureOpenLotteryRound($game);
    }

    public function createMatch(User $user, CreateMatchData $data): GameMatch
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::PLAY_RATE_KEY, $user->getKey()));

        $game = $this->games->findActiveBySlug($data->gameSlug);
        if ($game === null || ! $game->supports_multiplayer) {
            throw ValidationException::withMessages([
                'game_slug' => 'Multiplayer is not available for this game.',
            ]);
        }

        if (! in_array($game->slug, [Game::SLUG_LUDO, Game::SLUG_ROCK_PAPER_SCISSORS], true)) {
            throw ValidationException::withMessages([
                'game_slug' => 'Only Ludo and Rock Paper Scissors support matches.',
            ]);
        }

        $maxPlayers = max(2, min($data->maxPlayers, $game->slug === Game::SLUG_LUDO ? 4 : 2));
        $stake = max(0, $data->stakeCoins);
        if ($stake > 0 && ($stake < $game->min_bet_coins || $stake > $game->max_bet_coins)) {
            throw ValidationException::withMessages([
                'stake_coins' => "Stake must be between {$game->min_bet_coins} and {$game->max_bet_coins}.",
            ]);
        }

        $match = DB::transaction(function () use ($user, $game, $maxPlayers, $stake, $data): GameMatch {
            if ($stake > 0) {
                $wallet = $this->wallets->lockForUser($user);
                if ($wallet->coins_balance < $stake) {
                    throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
                }
            }

            $state = $this->initialMatchState($game->slug, $user->getKey());

            $match = $this->games->createMatch([
                'game_id' => $game->getKey(),
                'host_user_id' => $user->getKey(),
                'max_players' => $maxPlayers,
                'stake_coins' => $stake,
                'status' => GameMatch::STATUS_WAITING,
                'state' => $state,
                'metadata' => $data->metadata,
            ]);

            GameMatchPlayer::query()->create([
                'match_id' => $match->getKey(),
                'user_id' => $user->getKey(),
                'seat' => 0,
                'status' => GameMatchPlayer::STATUS_JOINED,
                'score' => 0,
                'state' => ['position' => 0],
            ]);

            if ($stake > 0) {
                $wallet = $this->wallets->lockForUser($user);
                $wallet->decrement('coins_balance', $stake);
                WalletTransaction::query()->create([
                    'user_id' => $user->getKey(),
                    'type' => GamePlay::WALLET_TYPE_GAME_BET,
                    'amount' => -$stake,
                    'currency' => WalletTransaction::CURRENCY_COINS,
                    'reference_type' => $match->getMorphClass(),
                    'reference_id' => $match->getKey(),
                    'metadata' => ['game_slug' => $game->slug, 'action' => 'match_stake'],
                    'created_at' => now(),
                ]);
            }

            return $match->fresh(['game', 'players.user.profile', 'host', 'winner']);
        });

        if ($stake > 0) {
            WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());
        }

        GameMatchUpdated::dispatch($match);

        return $match;
    }

    public function joinMatch(User $user, string $matchId): GameMatch
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::PLAY_RATE_KEY, $user->getKey()));

        $match = DB::transaction(function () use ($user, $matchId): GameMatch {
            $locked = GameMatch::query()->lockForUpdate()->find($matchId);
            if ($locked === null) {
                throw ValidationException::withMessages(['match' => 'Match not found.']);
            }

            if ($locked->status !== GameMatch::STATUS_WAITING) {
                throw ValidationException::withMessages(['match' => 'Match is not accepting players.']);
            }

            if ($locked->players()->where('user_id', $user->getKey())->exists()) {
                throw ValidationException::withMessages(['match' => 'You already joined this match.']);
            }

            $playerCount = $locked->players()->count();
            if ($playerCount >= $locked->max_players) {
                throw ValidationException::withMessages(['match' => 'Match is full.']);
            }

            $stake = (int) $locked->stake_coins;
            if ($stake > 0) {
                $wallet = $this->wallets->lockForUser($user);
                if ($wallet->coins_balance < $stake) {
                    throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
                }
                $wallet->decrement('coins_balance', $stake);
                WalletTransaction::query()->create([
                    'user_id' => $user->getKey(),
                    'type' => GamePlay::WALLET_TYPE_GAME_BET,
                    'amount' => -$stake,
                    'currency' => WalletTransaction::CURRENCY_COINS,
                    'reference_type' => $locked->getMorphClass(),
                    'reference_id' => $locked->getKey(),
                    'metadata' => ['action' => 'match_stake'],
                    'created_at' => now(),
                ]);
            }

            $seat = $playerCount;
            GameMatchPlayer::query()->create([
                'match_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'seat' => $seat,
                'status' => GameMatchPlayer::STATUS_JOINED,
                'score' => 0,
                'state' => ['position' => 0],
            ]);

            $state = $locked->state ?? [];
            $positions = $state['positions'] ?? [];
            $positions[$user->getKey()] = 0;
            $players = $state['players'] ?? [];
            $players[] = $user->getKey();
            $state['positions'] = $positions;
            $state['players'] = array_values(array_unique($players));
            $locked->forceFill(['state' => $state])->save();

            $freshCount = $locked->players()->count();
            if ($freshCount >= $locked->max_players) {
                $locked->forceFill([
                    'status' => GameMatch::STATUS_ACTIVE,
                    'started_at' => now(),
                ])->save();

                $locked->players()->update(['status' => GameMatchPlayer::STATUS_PLAYING]);
            }

            return $locked->fresh(['game', 'players.user.profile', 'host', 'winner']);
        });

        if ((int) $match->stake_coins > 0) {
            WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());
        }

        GameMatchUpdated::dispatch($match);

        return $match;
    }

    public function getMatch(string $matchId): GameMatch
    {
        $match = $this->games->findMatch($matchId);
        if ($match === null) {
            throw ValidationException::withMessages(['match' => 'Match not found.']);
        }

        return $match;
    }

    public function matchAction(User $user, string $matchId, MatchActionData $data): GameMatch
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::PLAY_RATE_KEY, $user->getKey()));

        $match = DB::transaction(function () use ($user, $matchId, $data): GameMatch {
            $locked = GameMatch::query()->lockForUpdate()->with('game')->find($matchId);
            if ($locked === null) {
                throw ValidationException::withMessages(['match' => 'Match not found.']);
            }

            if ($locked->status !== GameMatch::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['match' => 'Match is not active.']);
            }

            $player = GameMatchPlayer::query()
                ->where('match_id', $locked->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($player === null) {
                throw ValidationException::withMessages(['match' => 'You are not a participant.']);
            }

            $slug = $locked->game?->slug;
            if ($slug === Game::SLUG_LUDO) {
                return $this->handleLudoAction($locked, $player, $user, $data);
            }

            if ($slug === Game::SLUG_ROCK_PAPER_SCISSORS) {
                return $this->handleRpsMatchAction($locked, $player, $user, $data);
            }

            throw ValidationException::withMessages(['match' => 'Unsupported match game.']);
        });

        GameMatchUpdated::dispatch($match);

        return $match;
    }

    private function playWithEngine(User $user, Game $game, PlayGameData $data): PlayGameResult
    {
        $bet = max(0, $data->betCoins);
        if ($bet < $game->min_bet_coins || $bet > $game->max_bet_coins) {
            throw ValidationException::withMessages([
                'bet_coins' => "Bet must be between {$game->min_bet_coins} and {$game->max_bet_coins}.",
            ]);
        }

        $outcome = $this->gameManager->resolve($user, $game, $bet, $data->input);
        $bet = $outcome->betCoins;

        if ($bet < $game->min_bet_coins || $bet > $game->max_bet_coins) {
            throw ValidationException::withMessages([
                'bet_coins' => "Bet must be between {$game->min_bet_coins} and {$game->max_bet_coins}.",
            ]);
        }

        return $this->settlePlay($user, $game, $outcome, $data->metadata, $outcome->input);
    }

    private function playRedEnvelope(User $user, Game $game, PlayGameData $data): PlayGameResult
    {
        $action = strtolower((string) ($data->input['action'] ?? ''));

        return match ($action) {
            'create' => $this->createRedEnvelope($user, $game, $data),
            'claim' => $this->claimRedEnvelope($user, $game, $data),
            default => throw ValidationException::withMessages([
                'input.action' => 'Red envelope action must be create or claim.',
            ]),
        };
    }

    private function createRedEnvelope(User $user, Game $game, PlayGameData $data): PlayGameResult
    {
        $totalCoins = (int) ($data->input['total_coins'] ?? $data->betCoins);
        $totalSlots = (int) ($data->input['total_slots'] ?? 1);
        $message = isset($data->input['message']) ? (string) $data->input['message'] : null;

        if ($totalCoins < $game->min_bet_coins || $totalCoins > $game->max_bet_coins) {
            throw ValidationException::withMessages([
                'input.total_coins' => "Total coins must be between {$game->min_bet_coins} and {$game->max_bet_coins}.",
            ]);
        }

        if ($totalSlots < 1 || $totalSlots > 100) {
            throw ValidationException::withMessages(['input.total_slots' => 'Slots must be between 1 and 100.']);
        }

        if ($totalCoins < $totalSlots) {
            throw ValidationException::withMessages([
                'input.total_coins' => 'Total coins must be at least one per slot.',
            ]);
        }

        $result = DB::transaction(function () use ($user, $game, $totalCoins, $totalSlots, $message, $data): PlayGameResult {
            $wallet = $this->wallets->lockForUser($user);
            if ($wallet->coins_balance < $totalCoins) {
                throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
            }

            $envelope = RedEnvelope::query()->create([
                'game_id' => $game->getKey(),
                'sender_id' => $user->getKey(),
                'total_coins' => $totalCoins,
                'remaining_coins' => $totalCoins,
                'total_slots' => $totalSlots,
                'remaining_slots' => $totalSlots,
                'message' => $message,
                'status' => RedEnvelope::STATUS_OPEN,
                'expires_at' => now()->addHours((int) ($game->config['expires_hours'] ?? 24)),
            ]);

            $play = $this->games->createPlay([
                'game_id' => $game->getKey(),
                'user_id' => $user->getKey(),
                'bet_coins' => $totalCoins,
                'bet_diamonds' => 0,
                'reward_coins' => 0,
                'reward_diamonds' => 0,
                'result' => GamePlay::RESULT_LOST,
                'status' => GamePlay::STATUS_COMPLETED,
                'input' => [
                    'action' => 'create',
                    'total_coins' => $totalCoins,
                    'total_slots' => $totalSlots,
                    'message' => $message,
                ],
                'outcome' => [
                    'envelope_id' => $envelope->id,
                    'animation' => 'envelope_create',
                ],
                'metadata' => $data->metadata,
                'created_at' => now(),
            ]);

            $wallet->decrement('coins_balance', $totalCoins);
            $this->recordWalletTx($user, $play, -$totalCoins, GamePlay::WALLET_TYPE_GAME_BET, [
                'envelope_id' => $envelope->id,
                'action' => 'create',
            ]);

            $this->games->bumpLeaderboard($user, $game, $totalCoins, 0, false);
            $this->games->trackAnalytics($user, $game, 'red_envelope_create', [
                'envelope_id' => $envelope->id,
                'total_coins' => $totalCoins,
            ]);

            return new PlayGameResult(
                play: $play->loadMissing('game'),
                outcome: $play->outcome ?? [],
                wallet: $wallet->fresh(),
            );
        });

        GamePlayCompleted::dispatch($result->play);
        WalletUpdated::dispatch($result->wallet);

        return $result;
    }

    private function claimRedEnvelope(User $user, Game $game, PlayGameData $data): PlayGameResult
    {
        $envelopeId = (string) ($data->input['envelope_id'] ?? '');
        if ($envelopeId === '') {
            throw ValidationException::withMessages(['input.envelope_id' => 'Envelope id is required.']);
        }

        $result = DB::transaction(function () use ($user, $game, $envelopeId, $data): PlayGameResult {
            $envelope = RedEnvelope::query()->lockForUpdate()->find($envelopeId);
            if ($envelope === null) {
                throw ValidationException::withMessages(['input.envelope_id' => 'Envelope not found.']);
            }

            if ($envelope->status !== RedEnvelope::STATUS_OPEN || $envelope->remaining_slots < 1) {
                throw ValidationException::withMessages(['input.envelope_id' => 'Envelope is empty or closed.']);
            }

            if ($envelope->expires_at !== null && $envelope->expires_at->isPast()) {
                $envelope->forceFill(['status' => RedEnvelope::STATUS_EXPIRED])->save();
                throw ValidationException::withMessages(['input.envelope_id' => 'Envelope has expired.']);
            }

            if ($envelope->sender_id === $user->getKey()) {
                throw ValidationException::withMessages(['input.envelope_id' => 'You cannot claim your own envelope.']);
            }

            if (RedEnvelopeClaim::query()
                ->where('envelope_id', $envelope->getKey())
                ->where('user_id', $user->getKey())
                ->exists()) {
                throw ValidationException::withMessages(['input.envelope_id' => 'You already claimed this envelope.']);
            }

            $share = $this->randomEnvelopeShare(
                (int) $envelope->remaining_coins,
                (int) $envelope->remaining_slots,
            );

            RedEnvelopeClaim::query()->create([
                'envelope_id' => $envelope->getKey(),
                'user_id' => $user->getKey(),
                'coins' => $share,
                'created_at' => now(),
            ]);

            $remainingCoins = $envelope->remaining_coins - $share;
            $remainingSlots = $envelope->remaining_slots - 1;
            $envelope->forceFill([
                'remaining_coins' => $remainingCoins,
                'remaining_slots' => $remainingSlots,
                'status' => $remainingSlots < 1 ? RedEnvelope::STATUS_EMPTY : RedEnvelope::STATUS_OPEN,
            ])->save();

            $wallet = $this->wallets->lockForUser($user);
            $wallet->increment('coins_balance', $share);

            $play = $this->games->createPlay([
                'game_id' => $game->getKey(),
                'user_id' => $user->getKey(),
                'bet_coins' => 0,
                'bet_diamonds' => 0,
                'reward_coins' => $share,
                'reward_diamonds' => 0,
                'result' => GamePlay::RESULT_CLAIMED,
                'status' => GamePlay::STATUS_COMPLETED,
                'input' => [
                    'action' => 'claim',
                    'envelope_id' => $envelope->id,
                ],
                'outcome' => [
                    'envelope_id' => $envelope->id,
                    'coins' => $share,
                    'remaining_slots' => $remainingSlots,
                    'animation' => 'envelope_claim',
                ],
                'metadata' => $data->metadata,
                'created_at' => now(),
            ]);

            $this->recordWalletTx($user, $play, $share, GamePlay::WALLET_TYPE_GAME_REWARD, [
                'envelope_id' => $envelope->id,
                'action' => 'claim',
            ]);

            $this->games->bumpLeaderboard($user, $game, 0, $share, true);
            $this->games->trackAnalytics($user, $game, 'red_envelope_claim', [
                'envelope_id' => $envelope->id,
                'coins' => $share,
            ]);

            return new PlayGameResult(
                play: $play->loadMissing('game'),
                outcome: $play->outcome ?? [],
                wallet: $wallet->fresh(),
            );
        });

        GamePlayCompleted::dispatch($result->play);
        WalletUpdated::dispatch($result->wallet);

        return $result;
    }

    private function playLottery(User $user, Game $game, PlayGameData $data): PlayGameResult
    {
        $action = strtolower((string) ($data->input['action'] ?? 'buy'));
        $instant = (bool) ($game->config['instant'] ?? false);

        $result = DB::transaction(function () use ($user, $game, $data, $action, $instant): PlayGameResult {
            $round = $this->ensureOpenLotteryRound($game);
            $ticketPrice = (int) $round->ticket_price;
            $picks = (int) $round->picks;
            $maxNumber = (int) $round->max_number;

            $numbers = $data->input['numbers'] ?? null;
            if (! is_array($numbers) || count($numbers) !== $picks) {
                $numbers = $this->rng->sampleUnique(1, $maxNumber, $picks);
            } else {
                $numbers = array_values(array_unique(array_map('intval', $numbers)));
                sort($numbers);
                if (count($numbers) !== $picks) {
                    throw ValidationException::withMessages([
                        'input.numbers' => "Pick exactly {$picks} unique numbers.",
                    ]);
                }
                foreach ($numbers as $n) {
                    if ($n < 1 || $n > $maxNumber) {
                        throw ValidationException::withMessages([
                            'input.numbers' => "Numbers must be between 1 and {$maxNumber}.",
                        ]);
                    }
                }
            }

            $wallet = $this->wallets->lockForUser($user);
            if ($wallet->coins_balance < $ticketPrice) {
                throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
            }

            $ticket = LotteryTicket::query()->create([
                'round_id' => $round->getKey(),
                'user_id' => $user->getKey(),
                'numbers' => $numbers,
                'coins_paid' => $ticketPrice,
                'reward_coins' => 0,
                'status' => LotteryTicket::STATUS_PENDING,
                'created_at' => now(),
            ]);

            $wallet->decrement('coins_balance', $ticketPrice);

            $play = $this->games->createPlay([
                'game_id' => $game->getKey(),
                'user_id' => $user->getKey(),
                'bet_coins' => $ticketPrice,
                'bet_diamonds' => 0,
                'reward_coins' => 0,
                'reward_diamonds' => 0,
                'result' => GamePlay::RESULT_LOST,
                'status' => GamePlay::STATUS_COMPLETED,
                'input' => [
                    'action' => 'buy',
                    'numbers' => $numbers,
                    'round_id' => $round->id,
                ],
                'outcome' => [
                    'ticket_id' => $ticket->id,
                    'round_id' => $round->id,
                    'numbers' => $numbers,
                    'animation' => 'lottery_ticket',
                ],
                'metadata' => $data->metadata,
                'created_at' => now(),
            ]);

            $this->recordWalletTx($user, $play, -$ticketPrice, GamePlay::WALLET_TYPE_GAME_BET, [
                'ticket_id' => $ticket->id,
                'round_id' => $round->id,
            ]);

            $drawNow = $action === 'draw' || $instant;
            $drawOutcome = null;
            if ($drawNow) {
                $drawOutcome = $this->drawLotteryRound($round, $game);
                $ticket->refresh();
                if ($ticket->status === LotteryTicket::STATUS_WON) {
                    $play->forceFill([
                        'reward_coins' => $ticket->reward_coins,
                        'result' => GamePlay::RESULT_WON,
                        'outcome' => array_merge($play->outcome ?? [], [
                            'winning_numbers' => $drawOutcome['winning_numbers'],
                            'reward_coins' => $ticket->reward_coins,
                            'matches' => $drawOutcome['tickets'][$ticket->id]['matches'] ?? 0,
                            'animation' => 'lottery_win',
                        ]),
                    ])->save();
                } else {
                    $play->forceFill([
                        'outcome' => array_merge($play->outcome ?? [], [
                            'winning_numbers' => $drawOutcome['winning_numbers'],
                            'matches' => $drawOutcome['tickets'][$ticket->id]['matches'] ?? 0,
                            'animation' => 'lottery_lose',
                        ]),
                    ])->save();
                }
            }

            $this->games->bumpLeaderboard(
                $user,
                $game,
                $ticketPrice,
                (int) $play->reward_coins,
                $play->result === GamePlay::RESULT_WON,
            );
            $this->games->trackAnalytics($user, $game, 'lottery_buy', [
                'ticket_id' => $ticket->id,
                'round_id' => $round->id,
            ]);

            return new PlayGameResult(
                play: $play->fresh()->loadMissing('game'),
                outcome: $play->fresh()->outcome ?? [],
                wallet: $this->wallets->findOrCreateForUser($user)->fresh(),
            );
        });

        GamePlayCompleted::dispatch($result->play);
        WalletUpdated::dispatch($result->wallet);

        return $result;
    }

    /**
     * @return array{winning_numbers: list<int>, tickets: array<string, array{matches: int, reward: int}>}
     */
    private function drawLotteryRound(LotteryRound $round, Game $game): array
    {
        $locked = LotteryRound::query()->lockForUpdate()->findOrFail($round->getKey());
        if ($locked->status !== LotteryRound::STATUS_OPEN) {
            return [
                'winning_numbers' => $locked->winning_numbers ?? [],
                'tickets' => [],
            ];
        }

        $winning = $this->rng->sampleUnique(1, (int) $locked->max_number, (int) $locked->picks);
        $locked->forceFill([
            'winning_numbers' => $winning,
            'status' => LotteryRound::STATUS_DRAWN,
            'drawn_at' => now(),
        ])->save();

        $multipliers = $game->config['match_multipliers'] ?? [
            3 => 2,
            4 => 10,
            5 => 50,
            6 => 500,
        ];

        $ticketResults = [];
        $tickets = LotteryTicket::query()
            ->where('round_id', $locked->getKey())
            ->where('status', LotteryTicket::STATUS_PENDING)
            ->lockForUpdate()
            ->get();

        foreach ($tickets as $ticket) {
            $picked = $ticket->numbers ?? [];
            $matches = count(array_intersect($picked, $winning));
            $mult = (int) ($multipliers[(string) $matches] ?? $multipliers[$matches] ?? 0);
            $reward = (int) floor($ticket->coins_paid * $mult);
            $won = $reward > 0;

            $ticket->forceFill([
                'reward_coins' => $reward,
                'status' => $won ? LotteryTicket::STATUS_WON : LotteryTicket::STATUS_LOST,
            ])->save();

            if ($won) {
                $owner = User::query()->find($ticket->user_id);
                if ($owner !== null) {
                    $wallet = $this->wallets->lockForUser($owner);
                    $wallet->increment('coins_balance', $reward);
                    WalletTransaction::query()->create([
                        'user_id' => $owner->getKey(),
                        'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                        'amount' => $reward,
                        'currency' => WalletTransaction::CURRENCY_COINS,
                        'reference_type' => $ticket->getMorphClass(),
                        'reference_id' => $ticket->getKey(),
                        'metadata' => [
                            'round_id' => $locked->id,
                            'matches' => $matches,
                        ],
                        'created_at' => now(),
                    ]);
                    WalletUpdated::dispatch($wallet->fresh());
                }
            }

            $ticketResults[$ticket->id] = ['matches' => $matches, 'reward' => $reward];
        }

        $locked->forceFill(['status' => LotteryRound::STATUS_CLOSED])->save();

        return [
            'winning_numbers' => $winning,
            'tickets' => $ticketResults,
        ];
    }

    private function ensureOpenLotteryRound(Game $game): LotteryRound
    {
        $open = LotteryRound::query()
            ->where('game_id', $game->getKey())
            ->where('status', LotteryRound::STATUS_OPEN)
            ->orderByDesc('created_at')
            ->first();

        if ($open !== null) {
            return $open;
        }

        $config = $game->config ?? [];

        return LotteryRound::query()->create([
            'game_id' => $game->getKey(),
            'ticket_price' => (int) ($config['ticket_price'] ?? max($game->min_bet_coins, 50)),
            'max_number' => (int) ($config['max_number'] ?? 49),
            'picks' => (int) ($config['picks'] ?? 6),
            'status' => LotteryRound::STATUS_OPEN,
            'closes_at' => now()->addDay(),
        ]);
    }

    private function settlePlay(
        User $user,
        Game $game,
        EngineOutcome $outcome,
        ?array $metadata,
        array $input,
    ): PlayGameResult {
        $result = DB::transaction(function () use ($user, $game, $outcome, $metadata, $input): PlayGameResult {
            $wallet = $this->wallets->lockForUser($user);
            if ($wallet->coins_balance < $outcome->betCoins) {
                throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
            }

            $play = $this->games->createPlay([
                'game_id' => $game->getKey(),
                'user_id' => $user->getKey(),
                'bet_coins' => $outcome->betCoins,
                'bet_diamonds' => 0,
                'reward_coins' => $outcome->rewardCoins,
                'reward_diamonds' => $outcome->rewardDiamonds,
                'result' => $outcome->result,
                'status' => GamePlay::STATUS_COMPLETED,
                'input' => $input,
                'outcome' => $outcome->outcome,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            if ($outcome->betCoins > 0) {
                $wallet->decrement('coins_balance', $outcome->betCoins);
                $this->recordWalletTx($user, $play, -$outcome->betCoins, GamePlay::WALLET_TYPE_GAME_BET, [
                    'game_slug' => $game->slug,
                ]);
            }

            if ($outcome->rewardCoins > 0) {
                $wallet->increment('coins_balance', $outcome->rewardCoins);
                $this->recordWalletTx($user, $play, $outcome->rewardCoins, GamePlay::WALLET_TYPE_GAME_REWARD, [
                    'game_slug' => $game->slug,
                ]);
            }

            if ($outcome->rewardDiamonds > 0) {
                $wallet->increment('diamonds_balance', $outcome->rewardDiamonds);
                WalletTransaction::query()->create([
                    'user_id' => $user->getKey(),
                    'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                    'amount' => $outcome->rewardDiamonds,
                    'currency' => WalletTransaction::CURRENCY_DIAMONDS,
                    'reference_type' => $play->getMorphClass(),
                    'reference_id' => $play->getKey(),
                    'metadata' => ['game_slug' => $game->slug],
                    'created_at' => now(),
                ]);
            }

            $this->games->bumpLeaderboard(
                $user,
                $game,
                $outcome->betCoins,
                $outcome->rewardCoins,
                $outcome->result === GamePlay::RESULT_WON,
            );
            $this->games->trackAnalytics($user, $game, 'game_play', [
                'play_id' => $play->id,
                'result' => $outcome->result,
            ]);

            return new PlayGameResult(
                play: $play->loadMissing('game'),
                outcome: $outcome->outcome,
                wallet: $wallet->fresh(),
            );
        });

        GamePlayCompleted::dispatch($result->play);
        WalletUpdated::dispatch($result->wallet);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordWalletTx(
        User $user,
        GamePlay $play,
        int $amount,
        string $type,
        array $metadata = [],
    ): void {
        WalletTransaction::query()->create([
            'user_id' => $user->getKey(),
            'type' => $type,
            'amount' => $amount,
            'currency' => WalletTransaction::CURRENCY_COINS,
            'reference_type' => $play->getMorphClass(),
            'reference_id' => $play->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    private function randomEnvelopeShare(int $remainingCoins, int $remainingSlots): int
    {
        if ($remainingSlots <= 1) {
            return max(1, $remainingCoins);
        }

        $min = 1;
        $max = max($min, (int) floor(($remainingCoins - ($remainingSlots - 1)) * 2 / $remainingSlots));
        $max = min($max, $remainingCoins - ($remainingSlots - 1));

        return $this->rng->int($min, max($min, $max));
    }

    /** @return array<string, mixed> */
    private function initialMatchState(string $slug, string $hostUserId): array
    {
        if ($slug === Game::SLUG_LUDO) {
            return [
                'players' => [$hostUserId],
                'positions' => [$hostUserId => 0],
                'current_turn' => 0,
                'last_roll' => null,
                'finish_line' => self::LUDO_FINISH,
            ];
        }

        return [
            'players' => [$hostUserId],
            'choices' => [],
            'round' => 1,
        ];
    }

    private function handleLudoAction(
        GameMatch $match,
        GameMatchPlayer $player,
        User $user,
        MatchActionData $data,
    ): GameMatch {
        $action = strtolower($data->action);
        if ($action !== 'roll') {
            throw ValidationException::withMessages(['action' => 'Ludo action must be roll.']);
        }

        $state = $match->state ?? [];
        $currentTurn = (int) ($state['current_turn'] ?? 0);
        if ($player->seat !== $currentTurn) {
            throw ValidationException::withMessages(['action' => 'It is not your turn.']);
        }

        $dice = $this->rng->int(1, 6);
        $positions = $state['positions'] ?? [];
        $userId = $user->getKey();
        $currentPos = (int) ($positions[$userId] ?? 0);
        $newPos = min(self::LUDO_FINISH, $currentPos + $dice);
        $positions[$userId] = $newPos;

        $player->forceFill([
            'score' => $newPos,
            'state' => array_merge($player->state ?? [], ['position' => $newPos, 'last_roll' => $dice]),
        ])->save();

        $state['positions'] = $positions;
        $state['last_roll'] = ['user_id' => $userId, 'dice' => $dice];

        if ($newPos >= self::LUDO_FINISH) {
            return $this->finishMatch($match, $user, $state, $player);
        }

        $playerCount = $match->players()->count();
        $state['current_turn'] = ($currentTurn + 1) % max(1, $playerCount);
        $match->forceFill(['state' => $state])->save();

        return $match->fresh(['game', 'players.user.profile', 'host', 'winner']);
    }

    private function handleRpsMatchAction(
        GameMatch $match,
        GameMatchPlayer $player,
        User $user,
        MatchActionData $data,
    ): GameMatch {
        $action = strtolower($data->action);
        if ($action !== 'choose' && $action !== 'play') {
            throw ValidationException::withMessages(['action' => 'RPS action must be choose.']);
        }

        $choice = strtolower((string) ($data->payload['choice'] ?? ''));
        if (! in_array($choice, self::RPS_CHOICES, true)) {
            throw ValidationException::withMessages([
                'payload.choice' => 'Choice must be rock, paper, or scissors.',
            ]);
        }

        $state = $match->state ?? [];
        $choices = $state['choices'] ?? [];
        if (isset($choices[$user->getKey()])) {
            throw ValidationException::withMessages(['action' => 'You already chose for this round.']);
        }

        $choices[$user->getKey()] = $choice;
        $player->forceFill([
            'state' => array_merge($player->state ?? [], ['choice' => $choice]),
        ])->save();

        $state['choices'] = $choices;
        $players = $match->players()->get();

        if (count($choices) < $players->count()) {
            $match->forceFill(['state' => $state])->save();

            return $match->fresh(['game', 'players.user.profile', 'host', 'winner']);
        }

        $ids = $players->pluck('user_id')->all();
        $a = $ids[0];
        $b = $ids[1] ?? $ids[0];
        $result = $this->compareRps((string) $choices[$a], (string) $choices[$b]);

        $state['last_result'] = [
            'choices' => $choices,
            'result' => $result,
        ];

        if ($result === 'draw') {
            $state['choices'] = [];
            $state['round'] = ((int) ($state['round'] ?? 1)) + 1;
            $match->forceFill(['state' => $state])->save();
            foreach ($players as $p) {
                $p->forceFill(['state' => array_merge($p->state ?? [], ['choice' => null])])->save();
            }

            return $match->fresh(['game', 'players.user.profile', 'host', 'winner']);
        }

        $winnerId = $result === 'a' ? $a : $b;
        $winner = User::query()->findOrFail($winnerId);
        $winnerPlayer = $players->firstWhere('user_id', $winnerId);

        return $this->finishMatch($match, $winner, $state, $winnerPlayer);
    }

    private function compareRps(string $a, string $b): string
    {
        if ($a === $b) {
            return 'draw';
        }

        $wins = [
            'rock' => 'scissors',
            'paper' => 'rock',
            'scissors' => 'paper',
        ];

        return ($wins[$a] ?? '') === $b ? 'a' : 'b';
    }

    private function finishMatch(
        GameMatch $match,
        User $winner,
        array $state,
        ?GameMatchPlayer $winnerPlayer,
    ): GameMatch {
        $playerCount = $match->players()->count();
        $pot = (int) $match->stake_coins * $playerCount;

        $match->forceFill([
            'status' => GameMatch::STATUS_FINISHED,
            'winner_user_id' => $winner->getKey(),
            'finished_at' => now(),
            'state' => array_merge($state, ['pot' => $pot]),
        ])->save();

        $match->players()->where('user_id', '!=', $winner->getKey())
            ->update(['status' => GameMatchPlayer::STATUS_LOST]);

        if ($winnerPlayer !== null) {
            $winnerPlayer->forceFill(['status' => GameMatchPlayer::STATUS_WON])->save();
        } else {
            $match->players()->where('user_id', $winner->getKey())
                ->update(['status' => GameMatchPlayer::STATUS_WON]);
        }

        if ($pot > 0) {
            $wallet = $this->wallets->lockForUser($winner);
            $wallet->increment('coins_balance', $pot);
            WalletTransaction::query()->create([
                'user_id' => $winner->getKey(),
                'type' => GamePlay::WALLET_TYPE_GAME_REWARD,
                'amount' => $pot,
                'currency' => WalletTransaction::CURRENCY_COINS,
                'reference_type' => $match->getMorphClass(),
                'reference_id' => $match->getKey(),
                'metadata' => ['action' => 'match_pot', 'pot' => $pot],
                'created_at' => now(),
            ]);
            WalletUpdated::dispatch($wallet->fresh());
        }

        $game = $match->game;
        if ($game !== null) {
            $play = $this->games->createPlay([
                'game_id' => $game->getKey(),
                'user_id' => $winner->getKey(),
                'match_id' => $match->getKey(),
                'bet_coins' => (int) $match->stake_coins,
                'bet_diamonds' => 0,
                'reward_coins' => $pot,
                'reward_diamonds' => 0,
                'result' => GamePlay::RESULT_WON,
                'status' => GamePlay::STATUS_COMPLETED,
                'input' => ['match_id' => $match->id],
                'outcome' => ['pot' => $pot, 'animation' => 'match_win'],
                'metadata' => null,
                'created_at' => now(),
            ]);
            $this->games->bumpLeaderboard($winner, $game, (int) $match->stake_coins, $pot, true);
            GamePlayCompleted::dispatch($play);
        }

        return $match->fresh(['game', 'players.user.profile', 'host', 'winner']);
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== User::STATUS_ACTIVE) {
            throw new AuthorizationException('Only active registered users can play games.');
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
