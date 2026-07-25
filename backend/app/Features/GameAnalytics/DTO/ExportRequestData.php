<?php

namespace App\Features\GameAnalytics\DTO;

final readonly class ExportRequestData
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public string $format,
        public string $reportType,
        public AnalyticsFilterData $filters,
        public array $columns = [],
    ) {}
}
