<?php

namespace App\Features\Reports\Repositories\Eloquent;

use App\Features\Reports\DTOs\CreateReportData;
use App\Features\Reports\DTOs\ReviewReportData;
use App\Features\Reports\Models\Report;
use App\Features\Reports\Repositories\Contracts\ReportRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentReportRepository implements ReportRepository
{
    public function create(User $reporter, CreateReportData $data): Report
    {
        return Report::query()->create([
            'reporter_id' => $reporter->getKey(),
            'reported_user_id' => $data->reportedUserId,
            'category' => $data->category,
            'details' => $data->details,
            'status' => 'pending',
        ])->load('reportedUser.profile');
    }

    public function history(User $reporter, int $perPage): LengthAwarePaginator
    {
        return Report::query()
            ->whereBelongsTo($reporter, 'reporter')
            ->with('reportedUser.profile', 'reviewer.profile')
            ->latest()
            ->paginate($perPage);
    }

    public function pending(int $perPage): LengthAwarePaginator
    {
        return Report::query()
            ->with('reporter.profile', 'reportedUser.profile', 'reviewer.profile')
            ->latest()
            ->paginate($perPage);
    }

    public function review(Report $report, User $reviewer, ReviewReportData $data): Report
    {
        $report->update([
            'status' => $data->status,
            'review_notes' => $data->notes,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
        ]);

        return $report->fresh()->load('reporter.profile', 'reportedUser.profile', 'reviewer.profile');
    }
}
