<?php

namespace App\Features\Agency\Actions;

use App\Features\Agency\DTOs\ReviewApplicationData;
use App\Features\Agency\Models\Agency;
use App\Features\Agency\Models\AgencyMember;
use App\Features\Agency\Services\AgencyService;
use App\Features\Users\Models\User;

final readonly class ApproveAgencyApplication
{
    public function __construct(private AgencyService $agencies) {}

    public function execute(Agency $agency, User $reviewer, ReviewApplicationData $data): AgencyMember
    {
        return $this->agencies->approve($agency, $reviewer, $data);
    }
}
