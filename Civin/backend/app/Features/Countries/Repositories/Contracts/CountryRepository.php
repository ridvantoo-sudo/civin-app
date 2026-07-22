<?php

namespace App\Features\Countries\Repositories\Contracts;

use App\Features\Countries\Models\Country;
use Illuminate\Database\Eloquent\Collection;

interface CountryRepository
{
    public function active(): Collection;

    public function findActive(string $id): Country;
}
