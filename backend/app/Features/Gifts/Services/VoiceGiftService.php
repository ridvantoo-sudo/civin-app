<?php

namespace App\Features\Gifts\Services;

use App\Features\Blocking\Repositories\Contracts\BlockRepository;
use App\Features\Gifts\DTOs\SendGiftData;
use App\Features\Gifts\Events\GiftSent;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Gifts\Repositories\Contracts\GiftRepository;
use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceParticipant;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\Wallet\Events\GiftBalanceChanged;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class VoiceGiftService
{
    private const SEND_RATE_MAX = 10;

    private const SEND_RATE_DECAY_SECONDS = 60;

    public function __construct(
        private GiftRepository $gifts,
        private BlockRepository $blocks,
        private WalletRepository $wallets,
    ) {}

    public function send(VoiceRoom $room, User $sender, SendGiftData $data): GiftTransaction
    {
        $this->ensureCanSend($room, $sender);

        if ($data->clientRequestId !== null) {
            $existing = $this->existingRequest($sender, $data->clientRequestId);
            if ($existing !== null) {
                return $existing;
            }
        }

        $rateKey = "voice-gift-send:{$room->getKey()}:{$sender->getKey()}";
        if (RateLimiter::tooManyAttempts($rateKey, self::SEND_RATE_MAX)) {
            throw ValidationException::withMessages(['gift' => 'You are sending gifts too quickly.']);
        }
        RateLimiter::hit($rateKey, self::SEND_RATE_DECAY_SECONDS);

        $gift = $this->gifts->findActive($data->giftId);
        if ($gift === null) {
            throw ValidationException::withMessages(['gift_id' => 'The selected gift is unavailable.']);
        }

        $transaction = DB::transaction(function () use ($room, $sender, $data, $gift): GiftTransaction {
            $lockedRoom = VoiceRoom::query()->lockForUpdate()->findOrFail($room->getKey());
            if (! $lockedRoom->isLive()) {
                throw ValidationException::withMessages(['room' => 'Gifts can only be sent while the voice room is live.']);
            }

            if ($data->clientRequestId !== null) {
                $existing = $this->existingRequest($sender, $data->clientRequestId, true);
                if ($existing !== null) {
                    return $existing;
                }
            }

            $lockedSender = User::query()->lockForUpdate()->findOrFail($sender->getKey());
            $receiver = User::query()->lockForUpdate()->findOrFail($lockedRoom->host_id);
            if ($lockedSender->getKey() === $receiver->getKey()) {
                throw ValidationException::withMessages(['gift' => 'You cannot gift yourself.']);
            }

            $coins = $gift->coin_price * $data->quantity;
            $metadata = [...($data->metadata ?? []), 'room_type' => 'voice'];
            if ($data->clientRequestId !== null) {
                $metadata['client_request_id'] = $data->clientRequestId;
            }

            $transaction = GiftTransaction::query()->create([
                'sender_id' => $lockedSender->getKey(),
                'receiver_id' => $receiver->getKey(),
                'room_id' => null,
                'voice_room_id' => $lockedRoom->getKey(),
                'gift_id' => $gift->getKey(),
                'quantity' => $data->quantity,
                'coins' => $coins,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            $this->wallets->applyGiftTransfer($lockedSender, $receiver, $transaction, $coins);

            return $transaction->load([
                'sender.profile', 'sender.socialStatus',
                'receiver.profile', 'receiver.socialStatus',
                'gift.category',
            ]);
        });

        if ($transaction->wasRecentlyCreated) {
            GiftSent::dispatch($transaction);
            $this->broadcastBalances($transaction);
        }

        return $transaction;
    }

    private function ensureCanSend(VoiceRoom $room, User $sender): void
    {
        $active = $room->host_id === $sender->getKey()
            || VoiceParticipant::query()
                ->where('room_id', $room->getKey())
                ->where('user_id', $sender->getKey())
                ->whereNull('left_at')
                ->exists();
        if (! $active) {
            throw new AuthorizationException('Only an active voice-room participant can send gifts.');
        }
        if ($room->host_id !== $sender->getKey() && $this->blocks->existsBetween($sender, $room->host_id)) {
            throw ValidationException::withMessages(['room' => 'You cannot send gifts in this voice room.']);
        }
    }

    private function existingRequest(User $sender, string $requestId, bool $lock = false): ?GiftTransaction
    {
        $query = GiftTransaction::query()
            ->where('sender_id', $sender->getKey())
            ->where('metadata->client_request_id', $requestId);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->with([
            'sender.profile', 'sender.socialStatus',
            'receiver.profile', 'receiver.socialStatus',
            'gift.category',
        ])->first();
    }

    private function broadcastBalances(GiftTransaction $transaction): void
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
}
