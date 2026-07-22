<?php

namespace App\Features\Reports\Services;

use App\Features\Reports\DTOs\CreateReportData;
use App\Features\Reports\DTOs\ReviewReportData;
use App\Features\Reports\Events\ReportReviewed;
use App\Features\Reports\Events\UserReported;
use App\Features\Reports\Models\Report;
use App\Features\Reports\Notifications\ReportReviewedNotification;
use App\Features\Reports\Repositories\Contracts\ReportRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final readonly class ReportService
{
    public function __construct(private ReportRepository $reports) {}

    public function create(User $reporter, CreateReportData $data): Report
    {
        if ($reporter->getKey() === $data->reportedUserId) {
            throw ValidationException::withMessages(['user_id' => 'You cannot report yourself.']);
        }

        $report = $this->reports->create($reporter, $data);
        UserReported::dispatch($report);

        return $report;
    }

    public function history(User $reporter, int $perPage): LengthAwarePaginator
    {
        return $this->reports->history($reporter, $perPage);
    }

    public function adminIndex(int $perPage): LengthAwarePaginator
    {
        return $this->reports->pending($perPage);
    }

    public function review(Report $report, User $reviewer, ReviewReportData $data): Report
    {
        $reviewed = $this->reports->review($report, $reviewer, $data);
        ReportReviewed::dispatch($reviewed);
        $reviewed->reporter->notify(new ReportReviewedNotification($reviewed));

        return $reviewed;
    }
}
