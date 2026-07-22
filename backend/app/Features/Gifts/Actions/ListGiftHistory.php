<?php

namespace App\Features\Gifts\Actions;

use App\Features\Gifts\Services\GiftService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListGiftHistory
{
    public function __construct(private GiftService $gifts) {}

    public function execute(User $actor, User $subject, int $perPage): LengthAwarePaginator
    {
        return $this->gifts->history($actor, $subject, $perPage);
    }
}
