<?php

namespace App\Features\Gifts\Actions;

use App\Features\Gifts\Services\GiftService;
use Illuminate\Support\Collection;

final readonly class ListGifts
{
    public function __construct(private GiftService $gifts) {}

    public function execute(): Collection
    {
        return $this->gifts->catalog();
    }
}
