<?php

namespace App\Features\GameAnalytics\Actions;

use App\Features\GameAnalytics\DTO\AnalyticsFilterData;
use App\Features\GameAnalytics\Services\AnalyticsQueryService;

final readonly class GetAnalyticsRetention
{
    public function __construct(private AnalyticsQueryService $query) {}

    /** @return array<string, mixed> */
    public function execute(AnalyticsFilterData $filters): array
    {
        return $this->query->retention($filters);
    }
}
