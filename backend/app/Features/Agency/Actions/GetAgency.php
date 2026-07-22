<?php

namespace App\Features\Agency\Actions;

use App\Features\Agency\Models\Agency;
use App\Features\Agency\Services\AgencyService;

final readonly class GetAgency
{
    public function __construct(private AgencyService $agencies) {}

    public function execute(Agency $agency): Agency
    {
        return $this->agencies->show($agency);
    }
}
