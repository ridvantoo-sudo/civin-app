<?php

namespace App\Features\Gifts\Services;

use App\Features\Blocking\Repositories\Contracts\BlockRepository;
use App\Features\Gifts\DTOs\SendGiftData;
use App\Features\Gifts\Events\GiftSent;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Gifts\Repositories\Contracts\GiftRepository;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\GiftBalanceChanged;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class GiftService
{
    private const SEND_RATE_KEY = 'live-gift-send:%s:%s';

    private const SEND_RATE_MAX = 10;

    private const SEND_RATE_DECAY_SECONDS = 60;

    public function __construct(
        private GiftRepository $gifts,
        private BlockRepository $blocks,
        private WalletRepository $wallets,
    ) {}

    public function catalog(): Collection
    {
        return $this->gifts->activeCatalog();
    }

    public function send(LiveRoom $room, User $sender, SendGiftData $data): GiftTransaction
    {
        $this->ensureCanAccess($room, $sender);
        $this->ensureNotBlockedWithHost($room, $sender);

        if ($data->clientRequestId !== null) {
            $existing = $this->gifts->findTransactionByClientRequestId($sender, $data->clientRequestId);
            if ($existing !== null) {
                return $existing;
            }
        }

        $this->enforceRateLimit($room, $sender);

        $gift = $this->gifts->findActive($data->giftId);
        if ($gift === null) {
            throw ValidationException::withMessages(['gift_id' => 'The selected gift is unavailable.']);
        }

        $transaction = $this->gifts->send($room, $sender, $gift, $data);

        if ($transaction->wasRecentlyCreated) {
            GiftSent::dispatch($transaction);
            $this->broadcastGiftBalances($transaction);
        }

        return $transaction;
    }

    public function history(User $actor, User $subject, int $perPage): LengthAwarePaginator
    {
        if ($actor->getKey() !== $subject->getKey()) {
            throw new AuthorizationException('You can only view your own gift history.');
        }

        return $this->gifts->historyForUser($subject, $perPage);
    }

    private function broadcastGiftBalances(GiftTransaction $transaction): void
    {
        $senderWallet = $this->wallets->findOrCreateForUser($transaction->sender);
        $receiverWallet = $this->wallets->findOrCreateForUser($transaction->receiver);

        WalletUpdated::dispatch($senderWallet);
        WalletUpdated::dispatch($receiverWallet);

        GiftBalanceChanged::dispatch(
            $senderWallet,
            'debit',
            $transaction->coins,
            WalletTransaction::CURRENCY_COINS,
            $transaction->getKey(),
        );

        GiftBalanceChanged::dispatch(
            $receiverWallet,
            'credit',
            $transaction->coins,
            WalletTransaction::CURRENCY_DIAMONDS,
            $transaction->getKey(),
        );
    }

    private function ensureCanAccess(LiveRoom $room, User $user): void
    {
        if (! $this->gifts->isActiveParticipant($room, $user)) {
            throw new AuthorizationException('Only the host or an active viewer can send gifts.');
        }
    }

    private function ensureNotBlockedWithHost(LiveRoom $room, User $user): void
    {
        if ($room->host_id === $user->getKey()) {
            return;
        }

        if ($this->blocks->existsBetween($user, $room->host_id)) {
            throw ValidationException::withMessages(['room' => 'You cannot send gifts in this live room.']);
        }
    }

    private function enforceRateLimit(LiveRoom $room, User $user): void
    {
        $rateKey = sprintf(self::SEND_RATE_KEY, $room->getKey(), $user->getKey());

        if (RateLimiter::tooManyAttempts($rateKey, self::SEND_RATE_MAX)) {
            throw ValidationException::withMessages(['gift' => 'You are sending gifts too quickly.']);
        }

        RateLimiter::hit($rateKey, self::SEND_RATE_DECAY_SECONDS);
    }
}
