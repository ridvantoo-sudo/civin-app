<?php

namespace App\Features\GameAnalytics\DTO;

use Carbon\CarbonImmutable;

final readonly class AnalyticsFilterData
{
    public function __construct(
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?string $gameSlug = null,
        public ?string $countryCode = null,
        public ?string $devicePlatform = null,
        public ?string $roomType = null,
        public int $limit = 50,
    ) {}
}
