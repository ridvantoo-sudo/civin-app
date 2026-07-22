<?php

namespace App\Features\Vip\Actions;

use App\Features\Vip\Services\VipService;
use Illuminate\Support\Collection;

final readonly class ListVipLevels
{
    public function __construct(private VipService $vips) {}

    public function execute(): Collection
    {
        return $this->vips->levels();
    }
}
