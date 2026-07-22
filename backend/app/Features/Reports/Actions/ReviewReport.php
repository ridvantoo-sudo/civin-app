<?php

namespace App\Features\Reports\Actions;

use App\Features\Reports\DTOs\ReviewReportData;
use App\Features\Reports\Models\Report;
use App\Features\Reports\Services\ReportService;
use App\Features\Users\Models\User;

final readonly class ReviewReport
{
    public function __construct(private ReportService $reports) {}

    public function execute(Report $report, User $reviewer, ReviewReportData $data): Report
    {
        return $this->reports->review($report, $reviewer, $data);
    }
}
