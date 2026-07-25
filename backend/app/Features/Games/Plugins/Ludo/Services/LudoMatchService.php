<?php

namespace App\Features\Games\Plugins\Ludo\Services;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Plugins\Ludo\DTOs\CreateLudoMatchDTO;
use App\Features\Games\Plugins\Ludo\DTOs\JoinLudoMatchDTO;
use App\Features\Games\Plugins\Ludo\DTOs\LeaveLudoDTO;
use App\Features\Games\Plugins\Ludo\DTOs\MovePieceDTO;
use App\Features\Games\Plugins\Ludo\DTOs\ReadyLudoDTO;
use App\Features\Games\Plugins\Ludo\DTOs\ReconnectLudoDTO;
use App\Features\Games\Plugins\Ludo\DTOs\RollDiceDTO;
use App\Features\Games\Plugins\Ludo\Events\DiceRolled;
use App\Features\Games\Plugins\Ludo\Events\LudoMatchCancelled;
use App\Features\Games\Plugins\Ludo\Events\LudoMatchFinished;
use App\Features\Games\Plugins\Ludo\Events\LudoMatchStarted;
use App\Features\Games\Plugins\Ludo\Events\LudoPlayerJoined;
use App\Features\Games\Plugins\Ludo\Events\LudoPlayerReady;
use App\Features\Games\Plugins\Ludo\Events\LudoRewardGranted;
use App\Features\Games\Plugins\Ludo\Events\LudoRoomCreated;
use App\Features\Games\Plugins\Ludo\Events\PieceMoved;
use App\Features\Games\Plugins\Ludo\Events\PlayerReconnected;
use App\Features\Games\Plugins\Ludo\Events\TurnChanged;
use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use App\Features\Games\Plugins\Ludo\Models\LudoMove;
use App\Features\Games\Plugins\Ludo\Models\LudoPiece;
use App\Features\Games\Plugins\Ludo\Models\LudoPlayer;
use App\Features\Games\Plugins\Ludo\Models\LudoReplay;
use App\Features\Games\Plugins\Ludo\Models\LudoReward;
use App\Features\Games\Plugins\Ludo\Models\LudoTurn;
use App\Features\Games\Plugins\Ludo\Repositories\Contracts\LudoRepository;
use App\Features\Games\Plugins\Ludo\Support\LudoRuleEngine;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class LudoMatchService
{
    private const COLORS = ['red', 'green', 'yellow', 'blue'];

    public function __construct(
        private LudoRepository $repository,
        private WalletRepository $wallets,
        private LudoRuleEngine $rules,
    ) {}

    /** @return array{match:LudoMatch,wallet:Wallet,replay:bool} */
    public function create(User $user, CreateLudoMatchDTO $dto): array
    {
        $this->guard($user, 'create', 20);
        $existing = LudoMatch::query()->where('host_id', $user->getKey())->where('idempotency_key', $dto->idempotencyKey)->first();
        if ($existing) {
            return ['match' => $this->repository->match((string) $existing->getKey()), 'wallet' => $this->wallets->findOrCreateForUser($user), 'replay' => true];
        }

        $config = $this->repository->config();
        if (! $config?->isActive()) {
            throw ValidationException::withMessages(['config' => 'Ludo is unavailable.']);
        }
        if (! in_array($dto->mode, $config->modes_enabled, true) || ! in_array($dto->matchmaking, $config->matchmaking_enabled, true)) {
            throw ValidationException::withMessages(['mode' => 'Ludo mode or matchmaking is disabled.']);
        }
        if ($dto->entryFeeCoins < $config->min_entry_fee_coins || $dto->entryFeeCoins > $config->max_entry_fee_coins) {
            throw ValidationException::withMessages(['entry_fee_coins' => 'Invalid entry fee.']);
        }

        $result = DB::transaction(function () use ($user, $dto, $config): array {
            $wallet = $this->wallets->lockForUser($user);
            $this->assertFunds($wallet, $dto->entryFeeCoins);
            $game = Game::query()->where('slug', Game::SLUG_LUDO)->first();
            $match = $this->repository->createMatch([
                'config_id' => $config->getKey(), 'game_id' => $game?->getKey(),
                'host_id' => $user->getKey(), 'invite_code' => Str::upper(Str::random(8)),
                'idempotency_key' => $dto->idempotencyKey, 'mode' => $dto->mode,
                'matchmaking' => $dto->matchmaking, 'status' => LudoMatch::STATUS_WAITING,
                'max_players' => $dto->maxPlayers, 'players_count' => 1,
                'entry_fee_coins' => $dto->entryFeeCoins, 'pot_coins' => $dto->entryFeeCoins,
                'rules' => $this->rules->normalizeRules($config->rules ?? []),
                'room_id' => $dto->roomId, 'room_type' => $dto->roomType,
                'tournament_id' => $dto->tournamentId,
                'ready_deadline_at' => now()->addSeconds((int) $config->ready_timeout_seconds),
                'metadata' => $dto->metadata,
            ]);
            $player = LudoPlayer::query()->create([
                'match_id' => $match->getKey(), 'user_id' => $user->getKey(),
                'color' => 'red', 'seat' => 0, 'is_host' => true,
            ]);
            $this->createPieces($match, $player);
            $this->debit($wallet, $user, $match, $dto->entryFeeCoins, 'ludo_entry_fee');

            return ['match' => $match, 'wallet' => $wallet];
        });

        LudoRoomCreated::dispatch($result['match']);
        WalletUpdated::dispatch($result['wallet']->fresh());

        return ['match' => $this->repository->match((string) $result['match']->getKey()), 'wallet' => $result['wallet']->fresh(), 'replay' => false];
    }

    /** @return array{match:LudoMatch,wallet:Wallet,replay:bool} */
    public function join(User $user, JoinLudoMatchDTO $dto): array
    {
        $this->guard($user, 'join', 60);
        $result = DB::transaction(function () use ($user, $dto): array {
            $match = LudoMatch::query()->whereKey($dto->matchId)->lockForUpdate()->firstOrFail();
            $existing = LudoPlayer::query()->where('match_id', $match->getKey())->where('user_id', $user->getKey())->first();
            if ($existing) {
                return ['match' => $match, 'wallet' => $this->wallets->findOrCreateForUser($user), 'replay' => true];
            }
            if ($dto->spectator) {
                if (! $match->spectators_enabled) {
                    throw ValidationException::withMessages(['spectator' => 'Spectators are disabled.']);
                }
                LudoPlayer::query()->create(['match_id' => $match->getKey(), 'user_id' => $user->getKey(), 'seat' => 100 + $match->players()->count(), 'is_spectator' => true, 'metadata' => ['join_key' => $dto->idempotencyKey]]);

                return ['match' => $match, 'wallet' => $this->wallets->findOrCreateForUser($user), 'replay' => false];
            }
            if ($match->status !== LudoMatch::STATUS_WAITING || $match->players()->where('is_spectator', false)->count() >= $match->max_players) {
                throw ValidationException::withMessages(['match_id' => 'Match is not joinable.']);
            }
            if ($match->matchmaking === 'private' && ! hash_equals((string) $match->invite_code, (string) $dto->inviteCode)) {
                throw ValidationException::withMessages(['invite_code' => 'Invalid invite code.']);
            }
            $wallet = $this->wallets->lockForUser($user);
            $this->assertFunds($wallet, (int) $match->entry_fee_coins);
            $seat = $match->players()->where('is_spectator', false)->count();
            $player = LudoPlayer::query()->create([
                'match_id' => $match->getKey(), 'user_id' => $user->getKey(),
                'color' => self::COLORS[$seat], 'seat' => $seat,
                'metadata' => ['join_key' => $dto->idempotencyKey] + $dto->metadata,
            ]);
            $this->createPieces($match, $player);
            $match->increment('players_count');
            $match->increment('pot_coins', (int) $match->entry_fee_coins);
            $this->debit($wallet, $user, $match, (int) $match->entry_fee_coins, 'ludo_entry_fee');

            return ['match' => $match, 'wallet' => $wallet, 'replay' => false];
        });
        if (! $result['replay']) {
            LudoPlayerJoined::dispatch($result['match'], ['user_id' => $user->getKey()]);
            WalletUpdated::dispatch($result['wallet']->fresh());
        }

        return ['match' => $this->repository->match($dto->matchId), 'wallet' => $result['wallet']->fresh(), 'replay' => $result['replay']];
    }

    public function ready(User $user, ReadyLudoDTO $dto): LudoMatch
    {
        $start = DB::transaction(function () use ($user, $dto): bool {
            $match = LudoMatch::query()->whereKey($dto->matchId)->lockForUpdate()->firstOrFail();
            $player = $this->player($match, $user);
            $player->update(['is_ready' => $dto->ready, 'metadata' => array_merge($player->metadata ?? [], ['ready_key' => $dto->idempotencyKey])]);
            $players = $match->players()->where('is_spectator', false)->get();
            if ($players->count() >= 2 && $players->every->is_ready) {
                $this->startMatch($match, $players);

                return true;
            }

            return false;
        });
        $match = $this->repository->match($dto->matchId);
        LudoPlayerReady::dispatch($match, ['user_id' => $user->getKey()]);
        if ($start) {
            LudoMatchStarted::dispatch($match);
        }

        return $match;
    }

    /** @return array{match:LudoMatch,turn:LudoTurn,dice:int,legal_moves:array,replay:bool} */
    public function roll(User $user, RollDiceDTO $dto): array
    {
        $this->guard($user, 'roll', 120);
        $result = DB::transaction(function () use ($user, $dto): array {
            $match = LudoMatch::query()->whereKey($dto->matchId)->lockForUpdate()->firstOrFail();
            $player = $this->assertTurn($match, $user);
            $existing = LudoTurn::query()->where('match_id', $match->getKey())->where('turn_index', $match->turn_index)->first();
            if ($existing?->dice_value !== null) {
                return ['turn' => $existing, 'dice' => (int) $existing->dice_value, 'legal_moves' => $this->rules->legalMoves($match->board_state, $player->color, (int) $existing->dice_value), 'replay' => true];
            }
            $dice = $this->rules->rollDice();
            $board = $match->board_state;
            $sixes = (int) ($board['consecutive_sixes'][$player->color] ?? 0);
            $board['consecutive_sixes'][$player->color] = $dice === 6 ? $sixes + 1 : 0;
            $turn = $existing ?? LudoTurn::query()->create([
                'match_id' => $match->getKey(), 'player_id' => $player->getKey(),
                'turn_index' => $match->turn_index, 'color' => $player->color,
                'started_at' => now(), 'ends_at' => $match->turn_deadline_at,
            ]);
            $turn->update(['dice_value' => $dice, 'metadata' => ['roll_key' => $dto->idempotencyKey]]);
            $match->update(['dice_value' => $dice, 'board_state' => $board]);
            $legal = $this->rules->legalMoves($board, $player->color, $dice);
            $forfeitSixes = ($match->rules['three_sixes_forfeit'] ?? true) && $board['consecutive_sixes'][$player->color] >= 3;
            if ($forfeitSixes || $legal === []) {
                $turn->update(['status' => $forfeitSixes ? 'three_sixes' : 'skipped', 'completed_at' => now()]);
                $this->advanceTurn($match, $player->color);
            }

            return ['turn' => $turn, 'dice' => $dice, 'legal_moves' => $forfeitSixes ? [] : $legal, 'replay' => false];
        });
        $match = $this->repository->match($dto->matchId);
        DiceRolled::dispatch($match, ['dice' => $result['dice'], 'legal_moves' => $result['legal_moves']]);

        return ['match' => $match] + $result;
    }

    /** @return array{match:LudoMatch,move:LudoMove,replay:bool} */
    public function move(User $user, MovePieceDTO $dto): array
    {
        $this->guard($user, 'move', 180);
        $result = DB::transaction(function () use ($user, $dto): array {
            $match = LudoMatch::query()->whereKey($dto->matchId)->lockForUpdate()->firstOrFail();
            $player = $this->assertTurn($match, $user);
            $existing = LudoMove::query()->where('player_id', $player->getKey())->where('idempotency_key', $dto->idempotencyKey)->first();
            if ($existing) {
                return ['move' => $existing, 'replay' => true, 'finished' => false];
            }
            $turn = LudoTurn::query()->where('match_id', $match->getKey())->where('turn_index', $match->turn_index)->whereNotNull('dice_value')->lockForUpdate()->first();
            if (! $turn) {
                throw ValidationException::withMessages(['dice' => 'Roll the dice first.']);
            }
            try {
                $applied = $this->rules->applyMove($match->board_state, $player->color, $dto->pieceIndex, (int) $turn->dice_value);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['piece_index' => $e->getMessage()]);
            }
            $event = collect($applied['events'])->firstWhere('type', 'piece_moved');
            $move = LudoMove::query()->create([
                'match_id' => $match->getKey(), 'turn_id' => $turn->getKey(), 'player_id' => $player->getKey(),
                'piece_index' => $dto->pieceIndex, 'from_pos' => (string) $event['from'], 'to_pos' => (string) $event['to'],
                'dice_value' => $turn->dice_value, 'captured_piece' => $event['captures'],
                'granted_extra_turn' => $applied['extra_turn'], 'idempotency_key' => $dto->idempotencyKey,
                'metadata' => $dto->metadata,
            ]);
            $match->update(['board_state' => $applied['board'], 'dice_value' => null]);
            $this->syncPieces($match, $applied['board']);
            $turn->update(['status' => 'completed', 'completed_at' => now()]);
            $this->appendReplay($match, ['type' => 'move', 'at' => now()->toISOString(), 'data' => $event]);
            if ($applied['winner_color']) {
                $winner = $match->players()->where('color', $applied['winner_color'])->firstOrFail();
                $this->settle($match, $winner);

                return ['move' => $move, 'replay' => false, 'finished' => true];
            }
            if (! $applied['extra_turn']) {
                $this->advanceTurn($match, $player->color);
            } else {
                $match->increment('turn_index');
                $this->newTurn($match, $player);
            }

            return ['move' => $move, 'replay' => false, 'finished' => false];
        });
        $match = $this->repository->match($dto->matchId);
        PieceMoved::dispatch($match, ['move_id' => $result['move']->getKey()]);
        if ($result['finished']) {
            LudoMatchFinished::dispatch($match);
        } else {
            TurnChanged::dispatch($match, ['color' => $match->current_turn_color]);
        }

        return ['match' => $match] + $result;
    }

    public function reconnect(User $user, ReconnectLudoDTO $dto): LudoMatch
    {
        $match = LudoMatch::query()->findOrFail($dto->matchId);
        $player = $this->player($match, $user);
        $player->update(['is_connected' => true, 'disconnected_at' => null, 'reconnect_count' => $player->reconnect_count + 1]);
        PlayerReconnected::dispatch($match, ['user_id' => $user->getKey()]);

        return $this->repository->match($dto->matchId);
    }

    public function leave(User $user, LeaveLudoDTO $dto): LudoMatch
    {
        $cancelled = DB::transaction(function () use ($user, $dto): bool {
            $match = LudoMatch::query()->whereKey($dto->matchId)->lockForUpdate()->firstOrFail();
            $player = $this->player($match, $user);
            if ($match->status === LudoMatch::STATUS_WAITING) {
                $this->refund($user, $match, (int) $match->entry_fee_coins);
                $player->delete();
                $match->decrement('players_count');
                $match->decrement('pot_coins', (int) $match->entry_fee_coins);
                if ($player->is_host) {
                    $next = $match->players()->where('is_spectator', false)->orderBy('seat')->first();
                    if ($next) {
                        $next->update(['is_host' => true]);
                        $match->update(['host_id' => $next->user_id]);
                    } else {
                        $match->update(['status' => LudoMatch::STATUS_CANCELLED, 'finished_at' => now()]);
                    }
                }

                return $match->status === LudoMatch::STATUS_CANCELLED;
            }
            $player->update(['left_at' => now(), 'is_connected' => false]);
            $remaining = $match->players()->where('is_spectator', false)->whereNull('left_at')->get();
            if ($remaining->count() === 1) {
                $this->settle($match, $remaining->first());
            }

            return false;
        });
        $match = $this->repository->match($dto->matchId);
        if ($cancelled) {
            LudoMatchCancelled::dispatch($match);
        }

        return $match;
    }

    public function processDue(int $limit = 100): int
    {
        $matches = LudoMatch::query()->where('status', LudoMatch::STATUS_ACTIVE)
            ->where('turn_deadline_at', '<=', now())->limit($limit)->get();
        foreach ($matches as $candidate) {
            DB::transaction(function () use ($candidate): void {
                $match = LudoMatch::query()->whereKey($candidate->getKey())->lockForUpdate()->first();
                if (! $match || $match->turn_deadline_at?->isFuture()) {
                    return;
                }
                $player = $match->players()->where('color', $match->current_turn_color)->first();
                if ($player) {
                    $player->increment('afk_count');
                    if ($player->afk_count >= (int) ($match->config?->forfeit_after_afk ?? 3)) {
                        $player->update(['left_at' => now()]);
                    }
                    $this->advanceTurn($match, $player->color);
                }
            });
        }

        return $matches->count();
    }

    private function startMatch(LudoMatch $match, $players): void
    {
        $pieces = [];
        $users = [];
        $sixes = [];
        foreach ($players as $player) {
            $users[$player->color] = $player->user_id;
            $sixes[$player->color] = 0;
            $pieces[$player->color] = array_map(fn (int $index): array => ['index' => $index, 'position' => -1, 'status' => 'yard'], range(0, 3));
        }
        $board = ['players' => $users, 'pieces' => $pieces, 'consecutive_sixes' => $sixes, 'rules' => $match->rules];
        $first = $players->firstWhere('color', 'red') ?? $players->first();
        $match->update([
            'status' => LudoMatch::STATUS_ACTIVE, 'board_state' => $board,
            'current_turn_color' => $first->color, 'current_player_id' => $first->user_id,
            'turn_index' => 1, 'started_at' => now(), 'ready_deadline_at' => null,
        ]);
        $this->newTurn($match, $first);
        LudoReplay::query()->updateOrCreate(['match_id' => $match->getKey()], ['events' => [['type' => 'started', 'at' => now()->toISOString()]], 'started_at' => now()]);
    }

    private function advanceTurn(LudoMatch $match, string $color): void
    {
        $active = $match->players()->where('is_spectator', false)->whereNull('left_at')->orderBy('seat')->get();
        $nextColor = $this->rules->nextColor($color, $active->pluck('color')->all());
        $next = $active->firstWhere('color', $nextColor);
        $match->increment('turn_index');
        $match->update(['current_turn_color' => $nextColor, 'current_player_id' => $next->user_id, 'dice_value' => null]);
        $this->newTurn($match, $next);
    }

    private function newTurn(LudoMatch $match, LudoPlayer $player): void
    {
        $timeout = (int) ($this->repository->config()?->turn_timeout_seconds ?? 30);
        $deadline = now()->addSeconds($timeout);
        $match->update(['turn_deadline_at' => $deadline]);
        LudoTurn::query()->create([
            'match_id' => $match->getKey(), 'player_id' => $player->getKey(),
            'turn_index' => $match->turn_index, 'color' => $player->color,
            'status' => 'active', 'started_at' => now(), 'ends_at' => $deadline,
        ]);
    }

    private function assertTurn(LudoMatch $match, User $user): LudoPlayer
    {
        if ($match->status !== LudoMatch::STATUS_ACTIVE || $match->is_paused) {
            throw ValidationException::withMessages(['match_id' => 'Match is not active.']);
        }
        $player = $this->player($match, $user);
        if ((string) $match->current_player_id !== (string) $user->getKey()) {
            throw ValidationException::withMessages(['turn' => 'It is not your turn.']);
        }

        return $player;
    }

    private function player(LudoMatch $match, User $user): LudoPlayer
    {
        $player = $match->players()->where('user_id', $user->getKey())->where('is_spectator', false)->first();
        if (! $player) {
            throw new AuthorizationException('You are not a player in this Ludo match.');
        }

        return $player;
    }

    private function settle(LudoMatch $match, LudoPlayer $winner): void
    {
        if (in_array($match->status, [LudoMatch::STATUS_FINISHED, LudoMatch::STATUS_CANCELLED], true)) {
            return;
        }
        $rake = (int) floor($match->pot_coins * ((int) ($this->repository->config()?->house_rake_bps ?? 0)) / 10000);
        $reward = max(0, (int) $match->pot_coins - $rake);
        $user = User::query()->findOrFail($winner->user_id);
        $wallet = $this->wallets->lockForUser($user);
        $wallet->increment('coins_balance', $reward);
        $this->transaction($user, $match, $reward, GamePlay::WALLET_TYPE_GAME_REWARD, 'ludo_reward');
        $row = LudoReward::query()->create(['match_id' => $match->getKey(), 'user_id' => $user->getKey(), 'amount' => $reward, 'status' => 'granted', 'metadata' => ['rake' => $rake]]);
        $match->update(['status' => LudoMatch::STATUS_FINISHED, 'result' => 'winner', 'winner_id' => $user->getKey(), 'rake_coins' => $rake, 'finished_at' => now(), 'settled_at' => now()]);
        LudoReplay::query()->where('match_id', $match->getKey())->update(['finished_at' => now()]);
        LudoRewardGranted::dispatch($match, ['reward_id' => $row->getKey()]);
        DB::afterCommit(fn () => WalletUpdated::dispatch($wallet->fresh()));
    }

    private function createPieces(LudoMatch $match, LudoPlayer $player): void
    {
        if ($player->is_spectator) {
            return;
        }
        foreach (range(0, 3) as $index) {
            LudoPiece::query()->create(['match_id' => $match->getKey(), 'player_id' => $player->getKey(), 'color' => $player->color, 'piece_index' => $index]);
        }
    }

    private function syncPieces(LudoMatch $match, array $board): void
    {
        foreach ($board['pieces'] as $color => $pieces) {
            foreach ($pieces as $piece) {
                LudoPiece::query()->where('match_id', $match->getKey())->where('color', $color)->where('piece_index', $piece['index'])
                    ->update(['position' => (string) $piece['position'], 'status' => $piece['status']]);
            }
        }
    }

    private function appendReplay(LudoMatch $match, array $event): void
    {
        $replay = LudoReplay::query()->firstOrCreate(['match_id' => $match->getKey()], ['events' => []]);
        $events = $replay->events ?? [];
        $events[] = $event;
        $replay->update(['events' => $events]);
    }

    private function debit(Wallet $wallet, User $user, LudoMatch $match, int $amount, string $action): void
    {
        if ($amount <= 0) {
            return;
        }
        $wallet->decrement('coins_balance', $amount);
        $this->transaction($user, $match, -$amount, GamePlay::WALLET_TYPE_GAME_BET, $action);
    }

    private function refund(User $user, LudoMatch $match, int $amount): void
    {
        if ($amount <= 0 || LudoReward::query()->where('match_id', $match->getKey())->where('user_id', $user->getKey())->where('is_refund', true)->exists()) {
            return;
        }
        $wallet = $this->wallets->lockForUser($user);
        $wallet->increment('coins_balance', $amount);
        $this->transaction($user, $match, $amount, GamePlay::WALLET_TYPE_GAME_REWARD, 'ludo_refund');
        LudoReward::query()->create(['match_id' => $match->getKey(), 'user_id' => $user->getKey(), 'amount' => $amount, 'is_refund' => true, 'status' => 'refunded']);
        DB::afterCommit(fn () => WalletUpdated::dispatch($wallet->fresh()));
    }

    private function transaction(User $user, LudoMatch $match, int $amount, string $type, string $action): void
    {
        WalletTransaction::query()->create([
            'user_id' => $user->getKey(), 'type' => $type, 'amount' => $amount,
            'currency' => WalletTransaction::CURRENCY_COINS,
            'reference_type' => $match->getMorphClass(), 'reference_id' => $match->getKey(),
            'metadata' => ['game_slug' => Game::SLUG_LUDO, 'action' => $action, 'match_id' => $match->getKey()],
            'created_at' => now(),
        ]);
    }

    private function assertFunds(Wallet $wallet, int $amount): void
    {
        if ($amount > 0 && (int) $wallet->coins_balance < $amount) {
            throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
        }
    }

    private function guard(User $user, string $action, int $max): void
    {
        if ($user->status !== User::STATUS_ACTIVE || $user->is_guest) {
            throw new AuthorizationException('Not eligible to play Ludo.');
        }
        $key = "ludo:{$action}:{$user->getKey()}";
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages(['rate' => 'Too many requests.']);
        }
        RateLimiter::hit($key, 60);
    }
}
