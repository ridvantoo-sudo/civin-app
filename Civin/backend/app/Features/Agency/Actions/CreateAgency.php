<?php

namespace App\Features\Agency\Actions;

use App\Features\Agency\DTOs\CreateAgencyData;
use App\Features\Agency\Models\Agency;
use App\Features\Agency\Services\AgencyService;
use App\Features\Users\Models\User;

final readonly class CreateAgency
{
    public function __construct(private AgencyService $agencies) {}

    public function execute(User $owner, CreateAgencyData $data): Agency
    {
        return $this->agencies->create($owner, $data);
    }
}
