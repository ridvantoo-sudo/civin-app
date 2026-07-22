<?php

namespace App\Features\PkBattle\Services;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\PkBattle\DTOs\RequestPkBattleData;
use App\Features\PkBattle\Events\PkFinished;
use App\Features\PkBattle\Events\PkScoreUpdated;
use App\Features\PkBattle\Events\PkStarted;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Models\PkScore;
use App\Features\PkBattle\Repositories\Contracts\PkBattleRepository;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final readonly class PkBattleService
{
    public function __construct(
        private PkBattleRepository $battles,
        private WalletRepository $wallets,
    ) {}

    public function request(LiveRoom $room, User $host, RequestPkBattleData $data): PkBattle
    {
        $this->ensureHostOwnsRoom($room, $host);

        $opponent = LiveRoom::query()->find($data->opponentRoomId);
        if ($opponent === null) {
            throw ValidationException::withMessages([
                'opponent_room_id' => 'The selected opponent room is invalid.',
            ]);
        }

        return $this->battles->request($room, $opponent, $host, $data);
    }

    public function accept(LiveRoom $room, User $host): PkBattle
    {
        $this->ensureHostOwnsRoom($room, $host);

        $battle = PkBattle::query()
            ->where('status', PkBattle::STATUS_WAITING)
            ->where('room_b_id', $room->getKey())
            ->where('host_b_id', $host->getKey())
            ->latest('created_at')
            ->first();

        if ($battle === null) {
            throw ValidationException::withMessages([
                'battle' => 'No waiting PK request was found for this room.',
            ]);
        }

        return $this->battles->accept($battle, $room, $host);
    }

    public function start(PkBattle $battle, User $actor): PkBattle
    {
        $started = $this->battles->start($battle, $actor);
        PkStarted::dispatch($started);

        return $started;
    }

    public function end(PkBattle $battle, User $actor): PkBattle
    {
        $finished = $this->battles->end($battle, $actor);
        PkFinished::dispatch($finished);

        if ($finished->winner_id !== null) {
            $winner = User::query()->find($finished->winner_id);
            if ($winner !== null) {
                WalletUpdated::dispatch($this->wallets->findOrCreateForUser($winner));
            }
        }

        return $finished;
    }

    public function show(PkBattle $battle): PkBattle
    {
        return $this->battles->show($battle);
    }

    public function applyGiftScore(GiftTransaction $transaction): ?PkScore
    {
        $score = $this->battles->applyGiftScore($transaction);

        if ($score !== null) {
            PkScoreUpdated::dispatch($score->battle->fresh([
                'scores.user.profile', 'scores.user.socialStatus',
                'hostA.profile', 'hostA.socialStatus',
                'hostB.profile', 'hostB.socialStatus',
            ]), $score);
        }

        return $score;
    }

    private function ensureHostOwnsRoom(LiveRoom $room, User $host): void
    {
        if ($room->host_id !== $host->getKey()) {
            throw new AuthorizationException('Only the live host can manage PK battles for this room.');
        }
    }
}
