<?php

namespace App\Features\PkBattle\Repositories\Eloquent;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\PkBattle\DTOs\RequestPkBattleData;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Models\PkReward;
use App\Features\PkBattle\Models\PkScore;
use App\Features\PkBattle\Repositories\Contracts\PkBattleRepository;
use App\Features\Users\Models\User;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentPkBattleRepository implements PkBattleRepository
{
    public function __construct(private readonly WalletRepository $wallets) {}

    public function find(string $battleId): ?PkBattle
    {
        return PkBattle::query()->find($battleId);
    }

    public function show(PkBattle $battle): PkBattle
    {
        return $battle->load([
            'hostA.profile', 'hostA.socialStatus',
            'hostB.profile', 'hostB.socialStatus',
            'winner.profile', 'winner.socialStatus',
            'roomA', 'roomB',
            'scores.user.profile', 'scores.user.socialStatus',
            'rewards',
        ]);
    }

    public function findActiveForRoom(string $roomId): ?PkBattle
    {
        return PkBattle::query()
            ->whereIn('status', PkBattle::ACTIVE_STATUSES)
            ->where(function ($query) use ($roomId): void {
                $query->where('room_a_id', $roomId)->orWhere('room_b_id', $roomId);
            })
            ->latest('created_at')
            ->first();
    }

    public function findRunningForRoom(string $roomId): ?PkBattle
    {
        return PkBattle::query()
            ->where('status', PkBattle::STATUS_RUNNING)
            ->where(function ($query) use ($roomId): void {
                $query->where('room_a_id', $roomId)->orWhere('room_b_id', $roomId);
            })
            ->latest('created_at')
            ->first();
    }

    public function request(LiveRoom $roomA, LiveRoom $roomB, User $hostA, RequestPkBattleData $data): PkBattle
    {
        return DB::transaction(function () use ($roomA, $roomB, $hostA, $data): PkBattle {
            $lockedA = LiveRoom::query()->lockForUpdate()->findOrFail($roomA->getKey());
            $lockedB = LiveRoom::query()->lockForUpdate()->findOrFail($roomB->getKey());

            $this->ensureRoomIsLive($lockedA, 'room');
            $this->ensureRoomIsLive($lockedB, 'opponent_room_id');

            if ($lockedA->host_id !== $hostA->getKey()) {
                throw new AuthorizationException('Only the live host can request a PK battle.');
            }

            if ($lockedA->getKey() === $lockedB->getKey()) {
                throw ValidationException::withMessages([
                    'opponent_room_id' => 'You cannot challenge your own room.',
                ]);
            }

            if ($lockedA->host_id === $lockedB->host_id) {
                throw ValidationException::withMessages([
                    'opponent_room_id' => 'You cannot challenge yourself.',
                ]);
            }

            $this->ensureNoActiveBattle($lockedA->getKey(), 'room');
            $this->ensureNoActiveBattle($lockedB->getKey(), 'opponent_room_id');

            return PkBattle::query()->create([
                'room_a_id' => $lockedA->getKey(),
                'room_b_id' => $lockedB->getKey(),
                'host_a_id' => $lockedA->host_id,
                'host_b_id' => $lockedB->host_id,
                'status' => PkBattle::STATUS_WAITING,
                'duration_seconds' => $data->durationSeconds,
                'started_at' => null,
                'ended_at' => null,
                'winner_id' => null,
                'created_at' => now(),
            ])->load([
                'hostA.profile', 'hostA.socialStatus',
                'hostB.profile', 'hostB.socialStatus',
                'roomA', 'roomB',
                'scores',
                'rewards',
            ]);
        });
    }

    public function accept(PkBattle $battle, LiveRoom $roomB, User $hostB): PkBattle
    {
        return DB::transaction(function () use ($battle, $roomB, $hostB): PkBattle {
            $locked = PkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());

            if ($locked->status !== PkBattle::STATUS_WAITING) {
                throw ValidationException::withMessages([
                    'battle' => 'Only waiting PK battles can be accepted.',
                ]);
            }

            if ($locked->room_b_id !== $roomB->getKey()) {
                throw ValidationException::withMessages([
                    'room' => 'This PK request was not sent to this room.',
                ]);
            }

            if ($locked->host_b_id !== $hostB->getKey()) {
                throw new AuthorizationException('Only the challenged host can accept this PK battle.');
            }

            $lockedRoomA = LiveRoom::query()->lockForUpdate()->findOrFail($locked->room_a_id);
            $lockedRoomB = LiveRoom::query()->lockForUpdate()->findOrFail($locked->room_b_id);

            $this->ensureRoomIsLive($lockedRoomA, 'room');
            $this->ensureRoomIsLive($lockedRoomB, 'room');

            if ($locked->scores()->exists()) {
                return $this->show($locked);
            }

            $now = now();

            PkScore::query()->create([
                'pk_battle_id' => $locked->getKey(),
                'user_id' => $locked->host_a_id,
                'score' => 0,
                'gift_coins' => 0,
                'updated_at' => $now,
            ]);

            PkScore::query()->create([
                'pk_battle_id' => $locked->getKey(),
                'user_id' => $locked->host_b_id,
                'score' => 0,
                'gift_coins' => 0,
                'updated_at' => $now,
            ]);

            return $this->show($locked->fresh());
        });
    }

    public function start(PkBattle $battle, User $actor): PkBattle
    {
        return DB::transaction(function () use ($battle, $actor): PkBattle {
            $locked = PkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());

            if ($locked->status !== PkBattle::STATUS_WAITING) {
                throw ValidationException::withMessages([
                    'battle' => 'Only waiting PK battles can be started.',
                ]);
            }

            if (! $locked->involvesHost($actor->getKey())) {
                throw new AuthorizationException('Only PK hosts can start this battle.');
            }

            if (! $locked->scores()->exists()) {
                throw ValidationException::withMessages([
                    'battle' => 'The challenged host must accept the PK battle before it can start.',
                ]);
            }

            $lockedRoomA = LiveRoom::query()->lockForUpdate()->findOrFail($locked->room_a_id);
            $lockedRoomB = LiveRoom::query()->lockForUpdate()->findOrFail($locked->room_b_id);

            $this->ensureRoomIsLive($lockedRoomA, 'room');
            $this->ensureRoomIsLive($lockedRoomB, 'room');

            $locked->forceFill([
                'status' => PkBattle::STATUS_RUNNING,
                'started_at' => now(),
            ])->save();

            return $this->show($locked->fresh());
        });
    }

    public function end(PkBattle $battle, User $actor): PkBattle
    {
        return DB::transaction(function () use ($battle, $actor): PkBattle {
            $locked = PkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());

            if ($locked->status !== PkBattle::STATUS_RUNNING) {
                throw ValidationException::withMessages([
                    'battle' => 'Only running PK battles can be ended.',
                ]);
            }

            if (! $locked->involvesHost($actor->getKey())) {
                throw new AuthorizationException('Only PK hosts can end this battle.');
            }

            $scores = PkScore::query()
                ->where('pk_battle_id', $locked->getKey())
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            $scoreA = $scores->get($locked->host_a_id);
            $scoreB = $scores->get($locked->host_b_id);

            if ($scoreA === null || $scoreB === null) {
                throw ValidationException::withMessages([
                    'battle' => 'PK battle scores are incomplete.',
                ]);
            }

            $winnerId = null;
            if ($scoreA->score > $scoreB->score) {
                $winnerId = $locked->host_a_id;
            } elseif ($scoreB->score > $scoreA->score) {
                $winnerId = $locked->host_b_id;
            }

            $locked->forceFill([
                'status' => PkBattle::STATUS_FINISHED,
                'ended_at' => now(),
                'winner_id' => $winnerId,
            ])->save();

            if ($winnerId !== null) {
                $winnerScore = $scores->get($winnerId);
                $amount = max(1, (int) $winnerScore->score);

                $reward = PkReward::query()->create([
                    'pk_battle_id' => $locked->getKey(),
                    'winner_id' => $winnerId,
                    'reward_type' => PkReward::TYPE_DIAMONDS,
                    'amount' => $amount,
                    'created_at' => now(),
                ]);

                $this->wallets->applyPkReward(
                    User::query()->findOrFail($winnerId),
                    $reward,
                    $amount,
                );
            }

            return $this->show($locked->fresh());
        });
    }

    public function applyGiftScore(GiftTransaction $transaction): ?PkScore
    {
        return DB::transaction(function () use ($transaction): ?PkScore {
            $battle = PkBattle::query()
                ->where('status', PkBattle::STATUS_RUNNING)
                ->where(function ($query) use ($transaction): void {
                    $query->where('room_a_id', $transaction->room_id)
                        ->orWhere('room_b_id', $transaction->room_id);
                })
                ->lockForUpdate()
                ->latest('created_at')
                ->first();

            if ($battle === null) {
                return null;
            }

            if (! $battle->involvesHost($transaction->receiver_id)) {
                return null;
            }

            $score = PkScore::query()
                ->where('pk_battle_id', $battle->getKey())
                ->where('user_id', $transaction->receiver_id)
                ->lockForUpdate()
                ->first();

            if ($score === null) {
                return null;
            }

            $score->forceFill([
                'score' => $score->score + $transaction->coins,
                'gift_coins' => $score->gift_coins + $transaction->coins,
                'updated_at' => now(),
            ])->save();

            return $score->fresh(['battle', 'user.profile', 'user.socialStatus']);
        });
    }

    private function ensureRoomIsLive(LiveRoom $room, string $field): void
    {
        if ($room->status !== 'live') {
            throw ValidationException::withMessages([
                $field => 'PK battles require an active live room.',
            ]);
        }
    }

    private function ensureNoActiveBattle(string $roomId, string $field): void
    {
        $exists = PkBattle::query()
            ->whereIn('status', PkBattle::ACTIVE_STATUSES)
            ->where(function ($query) use ($roomId): void {
                $query->where('room_a_id', $roomId)->orWhere('room_b_id', $roomId);
            })
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                $field => 'This room already has an active PK battle.',
            ]);
        }
    }
}
