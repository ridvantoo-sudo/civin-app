<?php

namespace App\Features\Ai\Http\Controllers;

use App\Features\Ai\Actions\ManageAi;
use App\Features\Ai\DTOs\ModerateTextData;
use App\Features\Ai\DTOs\ModerateVoiceData;
use App\Features\Ai\DTOs\RecommendData;
use App\Features\Ai\DTOs\SuggestHighlightsData;
use App\Features\Ai\DTOs\TranslateData;
use App\Features\Ai\Http\Requests\ModerateTextRequest;
use App\Features\Ai\Http\Requests\ModerateVoiceRequest;
use App\Features\Ai\Http\Requests\RecommendRequest;
use App\Features\Ai\Http\Requests\SuggestHighlightsRequest;
use App\Features\Ai\Http\Requests\TranslateRequest;
use App\Features\Ai\Http\Resources\AiJobResource;
use App\Features\Ai\Models\AiJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AiController extends Controller
{
    public function moderateText(ModerateTextRequest $request, ManageAi $action): JsonResponse
    {
        $v = $request->validated();
        $job = $action->moderateText(
            $request->user(),
            new ModerateTextData(
                text: (string) $v['text'],
                surface: (string) ($v['surface'] ?? 'chat'),
                subjectType: $v['subject_type'] ?? null,
                subjectId: $v['subject_id'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new AiJobResource($job))->resolve($request),
        ], 201);
    }

    public function moderateVoice(ModerateVoiceRequest $request, ManageAi $action): JsonResponse
    {
        $v = $request->validated();
        $job = $action->moderateVoice(
            $request->user(),
            new ModerateVoiceData(
                surface: (string) ($v['surface'] ?? 'voice'),
                audioUrl: $v['audio_url'] ?? null,
                durationMs: isset($v['duration_ms']) ? (int) $v['duration_ms'] : null,
                roomId: $v['room_id'] ?? null,
                subjectType: $v['subject_type'] ?? null,
                subjectId: $v['subject_id'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new AiJobResource($job))->resolve($request),
        ], 201);
    }

    public function translate(TranslateRequest $request, ManageAi $action): JsonResponse
    {
        $v = $request->validated();
        $job = $action->translate(
            $request->user(),
            new TranslateData(
                text: (string) $v['text'],
                targetLocale: (string) $v['target_locale'],
                sourceLocale: $v['source_locale'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new AiJobResource($job))->resolve($request),
        ], 201);
    }

    public function recommend(RecommendRequest $request, ManageAi $action): JsonResponse
    {
        $v = $request->validated();
        $job = $action->recommend(
            $request->user(),
            new RecommendData(
                context: (string) ($v['context'] ?? 'discovery'),
                limit: (int) ($v['limit'] ?? 10),
                payload: $v['payload'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new AiJobResource($job))->resolve($request),
        ], 201);
    }

    public function suggestHighlights(SuggestHighlightsRequest $request, ManageAi $action): JsonResponse
    {
        $v = $request->validated();
        $job = $action->suggestHighlights(
            $request->user(),
            new SuggestHighlightsData(
                roomId: (string) $v['room_id'],
                replayId: $v['replay_id'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new AiJobResource($job))->resolve($request),
        ], 201);
    }

    public function showJob(Request $request, AiJob $job, ManageAi $action): JsonResponse
    {
        $job = $action->job($request->user(), $job);

        return response()->json([
            'data' => (new AiJobResource($job))->resolve($request),
        ]);
    }
}
