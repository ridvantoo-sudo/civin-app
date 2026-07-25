<?php

namespace App\Features\Svip\Actions;

use App\Features\Svip\Services\SvipService;
use Illuminate\Support\Collection;

final readonly class ListSvipLevels
{
    public function __construct(private SvipService $svips) {}

    public function execute(): Collection
    {
        return $this->svips->levels();
    }
}
