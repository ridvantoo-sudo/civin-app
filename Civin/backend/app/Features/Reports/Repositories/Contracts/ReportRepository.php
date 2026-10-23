<?php

namespace App\Features\Reports\Repositories\Contracts;

use App\Features\Reports\DTOs\CreateReportData;
use App\Features\Reports\DTOs\ReviewReportData;
use App\Features\Reports\Models\Report;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReportRepository
{
    public function create(User $reporter, CreateReportData $data): Report;

    public function history(User $reporter, int $perPage): LengthAwarePaginator;

    public function pending(int $perPage): LengthAwarePaginator;

    public function review(Report $report, User $reviewer, ReviewReportData $data): Report;
}
