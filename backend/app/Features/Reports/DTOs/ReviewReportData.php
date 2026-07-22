<?php

namespace App\Features\Reports\DTOs;

final readonly class ReviewReportData
{
    public function __construct(
        public string $status,
        public ?string $notes,
    ) {}
}
