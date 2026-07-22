<?php

namespace App\Features\Reports\DTOs;

final readonly class CreateReportData
{
    public function __construct(
        public string $reportedUserId,
        public string $category,
        public ?string $details,
    ) {}
}
