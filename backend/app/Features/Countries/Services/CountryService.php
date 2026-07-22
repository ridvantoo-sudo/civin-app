<?php

namespace App\Features\Countries\Services;

use App\Features\Countries\Models\Country;
use App\Features\Countries\Repositories\Contracts\CountryRepository;
use Illuminate\Database\Eloquent\Collection;

final readonly class CountryService
{
    public function __construct(private CountryRepository $countries) {}

    public function active(): Collection
    {
        return $this->countries->active();
    }

    public function show(string $id): Country
    {
        return $this->countries->findActive($id);
    }
}
