<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Actions\ReportUser;
use App\Features\Reports\Actions\ReviewReport;
use App\Features\Reports\DTOs\CreateReportData;
use App\Features\Reports\DTOs\ReviewReportData;
use App\Features\Reports\Http\Requests\CreateReportRequest;
use App\Features\Reports\Http\Requests\ListReportsRequest;
use App\Features\Reports\Http\Requests\ReviewReportRequest;
use App\Features\Reports\Http\Resources\ReportResource;
use App\Features\Reports\Models\Report;
use App\Features\Reports\Services\ReportService;
use App\Features\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function categories(): JsonResponse
    {
        return response()->json(['data' => CreateReportRequest::CATEGORIES]);
    }

    public function store(CreateReportRequest $request, User $user, ReportUser $action): ReportResource
    {
        return new ReportResource($action->execute(
            $request->user(),
            new CreateReportData(
                (string) $request->validated('user_id'),
                (string) $request->validated('category'),
                $request->validated('details'),
            ),
        ));
    }

    public function history(ListReportsRequest $request): AnonymousResourceCollection
    {
        return ReportResource::collection($this->reports->history($request->user(), $request->perPage()));
    }

    public function adminIndex(ListReportsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('reviewAny', Report::class);

        return ReportResource::collection($this->reports->adminIndex($request->perPage()));
    }

    public function review(
        ReviewReportRequest $request,
        Report $report,
        ReviewReport $action,
    ): ReportResource {
        return new ReportResource($action->execute(
            $report,
            $request->user(),
            new ReviewReportData(
                (string) $request->validated('status'),
                $request->validated('review_notes'),
            ),
        ));
    }
}
