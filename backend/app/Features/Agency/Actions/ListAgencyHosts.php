<?php

namespace App\Features\Agency\Actions;

use App\Features\Agency\Models\Agency;
use App\Features\Agency\Services\AgencyService;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

final readonly class ListAgencyHosts
{
    public function __construct(private AgencyService $agencies) {}

    public function execute(Agency $agency, User $actor): Collection
    {
        return $this->agencies->hosts($agency, $actor);
    }
}
