<?php

namespace App\Features\Gifts\Repositories\Eloquent;

use App\Features\Gifts\DTOs\SendGiftData;
use App\Features\Gifts\Models\Gift;
use App\Features\Gifts\Models\GiftCategory;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Gifts\Repositories\Contracts\GiftRepository;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveViewer;
use App\Features\Users\Models\User;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentGiftRepository implements GiftRepository
{
    public function __construct(private readonly WalletRepository $wallets) {}

    public function activeCatalog(): Collection
    {
        return Gift::query()
            ->where('status', Gift::STATUS_ACTIVE)
            ->whereHas('category', fn ($query) => $query->where('status', GiftCategory::STATUS_ACTIVE))
            ->with('category')
            ->orderBy('coin_price')
            ->orderBy('name')
            ->get();
    }

    public function findActive(string $giftId): ?Gift
    {
        return Gift::query()
            ->whereKey($giftId)
            ->where('status', Gift::STATUS_ACTIVE)
            ->whereHas('category', fn ($query) => $query->where('status', GiftCategory::STATUS_ACTIVE))
            ->with('category')
            ->first();
    }

    public function findTransactionByClientRequestId(User $sender, string $clientRequestId): ?GiftTransaction
    {
        return GiftTransaction::query()
            ->where('sender_id', $sender->getKey())
            ->where('metadata->client_request_id', $clientRequestId)
            ->with(['sender.profile', 'sender.socialStatus', 'receiver.profile', 'receiver.socialStatus', 'gift.category'])
            ->first();
    }

    public function send(LiveRoom $room, User $sender, Gift $gift, SendGiftData $data): GiftTransaction
    {
        return DB::transaction(function () use ($room, $sender, $gift, $data): GiftTransaction {
            $lockedRoom = LiveRoom::query()->lockForUpdate()->findOrFail($room->getKey());

            if ($lockedRoom->status !== 'live') {
                throw ValidationException::withMessages(['room' => 'Gifts can only be sent while the room is live.']);
            }

            if (! $this->isActiveParticipant($lockedRoom, $sender)) {
                throw ValidationException::withMessages(['room' => 'Only the host or an active viewer can send gifts.']);
            }

            $receiverId = $lockedRoom->host_id;

            if ($sender->getKey() === $receiverId) {
                throw ValidationException::withMessages(['gift' => 'You cannot gift yourself.']);
            }

            if ($data->clientRequestId !== null) {
                $existing = GiftTransaction::query()
                    ->where('sender_id', $sender->getKey())
                    ->where('metadata->client_request_id', $data->clientRequestId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing->load([
                        'sender.profile', 'sender.socialStatus',
                        'receiver.profile', 'receiver.socialStatus',
                        'gift.category',
                    ]);
                }
            }

            $lockedSender = User::query()->lockForUpdate()->findOrFail($sender->getKey());
            $lockedReceiver = User::query()->lockForUpdate()->findOrFail($receiverId);
            $coins = $gift->coin_price * $data->quantity;

            $senderWallet = $this->wallets->lockForUser($lockedSender);
            if ($senderWallet->coins_balance < $coins) {
                throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
            }

            $metadata = $data->metadata ?? [];
            if ($data->clientRequestId !== null) {
                $metadata['client_request_id'] = $data->clientRequestId;
            }

            $transaction = GiftTransaction::query()->create([
                'sender_id' => $lockedSender->getKey(),
                'receiver_id' => $lockedReceiver->getKey(),
                'room_id' => $lockedRoom->getKey(),
                'gift_id' => $gift->getKey(),
                'quantity' => $data->quantity,
                'coins' => $coins,
                'metadata' => $metadata === [] ? null : $metadata,
                'created_at' => now(),
            ]);

            $this->wallets->applyGiftTransfer($lockedSender, $lockedReceiver, $transaction, $coins);

            return $transaction->load([
                'sender.profile', 'sender.socialStatus',
                'receiver.profile', 'receiver.socialStatus',
                'gift.category',
            ]);
        });
    }

    public function historyForUser(User $user, int $perPage): LengthAwarePaginator
    {
        return GiftTransaction::query()
            ->where(function ($query) use ($user): void {
                $query->where('sender_id', $user->getKey())
                    ->orWhere('receiver_id', $user->getKey());
            })
            ->with(['sender.profile', 'sender.socialStatus', 'receiver.profile', 'receiver.socialStatus', 'gift.category'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function isActiveParticipant(LiveRoom $room, User $user): bool
    {
        if ($room->host_id === $user->getKey()) {
            return true;
        }

        return LiveViewer::query()
            ->where('room_id', $room->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->exists();
    }
}
