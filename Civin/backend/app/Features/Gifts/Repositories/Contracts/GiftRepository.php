<?php

namespace App\Features\Gifts\Repositories\Contracts;

use App\Features\Gifts\DTOs\SendGiftData;
use App\Features\Gifts\Models\Gift;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface GiftRepository
{
    /** @return Collection<int, Gift> */
    public function activeCatalog(): Collection;

    public function findActive(string $giftId): ?Gift;

    public function findTransactionByClientRequestId(User $sender, string $clientRequestId): ?GiftTransaction;

    public function send(LiveRoom $room, User $sender, Gift $gift, SendGiftData $data): GiftTransaction;

    public function historyForUser(User $user, int $perPage): LengthAwarePaginator;

    public function isActiveParticipant(LiveRoom $room, User $user): bool;
}
