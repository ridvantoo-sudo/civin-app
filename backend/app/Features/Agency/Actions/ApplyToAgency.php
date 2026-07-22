<?php

namespace App\Features\Agency\Actions;

use App\Features\Agency\DTOs\ApplyAgencyData;
use App\Features\Agency\Models\Agency;
use App\Features\Agency\Models\AgencyMember;
use App\Features\Agency\Services\AgencyService;
use App\Features\Users\Models\User;

final readonly class ApplyToAgency
{
    public function __construct(private AgencyService $agencies) {}

    public function execute(Agency $agency, User $user, ApplyAgencyData $data): AgencyMember
    {
        return $this->agencies->apply($agency, $user, $data);
    }
}
