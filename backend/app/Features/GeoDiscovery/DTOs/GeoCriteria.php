<?php

namespace App\Features\GeoDiscovery\DTOs;

final readonly class GeoCriteria
{
    public function __construct(
        public int $perPage = 20,
        public float $radiusKm = 25.0,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $country = null,
        public string $period = 'DAILY',
        public string $rankingType = 'HOST_DIAMONDS',
        public int $limit = 50,
    ) {}
}
