<?php

namespace App\Features\Agency\Actions;

use App\Features\Agency\Models\Agency;
use App\Features\Agency\Services\AgencyService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListAgencyEarnings
{
    public function __construct(private AgencyService $agencies) {}

    public function execute(Agency $agency, User $actor, int $perPage = 20): LengthAwarePaginator
    {
        return $this->agencies->earnings($agency, $actor, $perPage);
    }
}
