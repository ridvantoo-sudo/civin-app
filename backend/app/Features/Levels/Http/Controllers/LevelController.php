<?php

namespace App\Features\Levels\Http\Controllers;

use App\Features\Levels\Actions\ClaimDailyTask;
use App\Features\Levels\Actions\GetMyLevelProgress;
use App\Features\Levels\Actions\ListBadges;
use App\Features\Levels\Actions\ListDailyTasks;
use App\Features\Levels\Actions\ListXpHistory;
use App\Features\Levels\Http\Requests\ClaimDailyTaskRequest;
use App\Features\Levels\Http\Resources\LevelProgressResource;
use App\Features\Levels\Http\Resources\XpTransactionResource;
use App\Features\Levels\Support\XpCurve;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LevelController extends Controller
{
    public function me(Request $request, GetMyLevelProgress $action): JsonResponse
    {
        $progress = $action->execute($request->user());

        return response()->json([
            'data' => (new LevelProgressResource($progress))->resolve($request),
        ]);
    }

    public function history(Request $request, ListXpHistory $action): AnonymousResourceCollection
    {
        return XpTransactionResource::collection(
            $action->execute($request->user(), (int) $request->integer('per_page', 30)),
        );
    }

    public function dailyTasks(Request $request, ListDailyTasks $action): JsonResponse
    {
        return response()->json([
            'data' => $action->execute($request->user())->values()->all(),
        ]);
    }

    public function claimDailyTask(ClaimDailyTaskRequest $request, ClaimDailyTask $action): JsonResponse
    {
        $result = $action->execute(
            $request->user(),
            (string) $request->validated('task_id'),
        );

        return response()->json([
            'data' => [
                'awarded' => $result->awarded,
                'amount' => $result->amount,
                'leveled_up' => $result->leveledUp,
                'levels_gained' => $result->levelsGained,
                'progress' => (new LevelProgressResource($result->progress))->resolve($request),
            ],
        ]);
    }

    public function badges(Request $request, ListBadges $action): JsonResponse
    {
        return response()->json([
            'data' => $action->execute($request->user())->values()->all(),
        ]);
    }

    public function curve(): JsonResponse
    {
        $levels = [];
        for ($level = XpCurve::minLevel(); $level <= XpCurve::maxLevel(); $level++) {
            $levels[] = [
                'level' => $level,
                'xp_required' => XpCurve::xpRequiredForLevel($level),
                'xp_total' => XpCurve::totalXpForLevel($level),
            ];
        }

        return response()->json([
            'data' => [
                'min_level' => XpCurve::minLevel(),
                'max_level' => XpCurve::maxLevel(),
                'levels' => $levels,
            ],
        ]);
    }
}
