<?php

namespace App\Features\Reports\Actions;

use App\Features\Reports\DTOs\CreateReportData;
use App\Features\Reports\Models\Report;
use App\Features\Reports\Services\ReportService;
use App\Features\Users\Models\User;

final readonly class ReportUser
{
    public function __construct(private ReportService $reports) {}

    public function execute(User $reporter, CreateReportData $data): Report
    {
        return $this->reports->create($reporter, $data);
    }
}
