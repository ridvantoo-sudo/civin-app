<?php

namespace App\Features\CreatorTools\Http\Controllers;

use App\Features\CreatorTools\Actions\ManageCreatorTools;
use App\Features\CreatorTools\DTOs\CreateScheduleData;
use App\Features\CreatorTools\Http\Requests\CreateScheduleRequest;
use App\Features\CreatorTools\Http\Resources\StreamScheduleResource;
use App\Features\CreatorTools\Models\StreamSchedule;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StreamScheduleController extends Controller
{
    public function index(Request $request, ManageCreatorTools $action): AnonymousResourceCollection
    {
        return StreamScheduleResource::collection(
            $action->mySchedules($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function store(CreateScheduleRequest $request, ManageCreatorTools $action): JsonResponse
    {
        $v = $request->validated();
        $schedule = $action->createSchedule(
            $request->user(),
            new CreateScheduleData(
                title: (string) $v['title'],
                scheduledAt: new \DateTimeImmutable($v['scheduled_at']),
                timezone: (string) ($v['timezone'] ?? 'UTC'),
                categoryId: isset($v['category_id']) ? (int) $v['category_id'] : null,
                description: $v['description'] ?? null,
                thumbnail: $v['thumbnail'] ?? null,
                metadata: $v['metadata'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new StreamScheduleResource($schedule))->resolve($request),
        ], 201);
    }

    public function cancel(Request $request, StreamSchedule $schedule, ManageCreatorTools $action): JsonResponse
    {
        $schedule = $action->cancelSchedule($request->user(), $schedule);

        return response()->json([
            'data' => (new StreamScheduleResource($schedule))->resolve($request),
        ]);
    }
}
