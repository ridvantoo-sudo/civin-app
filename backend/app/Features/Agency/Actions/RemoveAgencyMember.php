<?php

namespace App\Features\Agency\Actions;

use App\Features\Agency\Models\Agency;
use App\Features\Agency\Models\AgencyMember;
use App\Features\Agency\Services\AgencyService;
use App\Features\Users\Models\User;

final readonly class RemoveAgencyMember
{
    public function __construct(private AgencyService $agencies) {}

    public function execute(Agency $agency, User $actor, User $memberUser): AgencyMember
    {
        return $this->agencies->removeMember($agency, $actor, $memberUser);
    }
}
