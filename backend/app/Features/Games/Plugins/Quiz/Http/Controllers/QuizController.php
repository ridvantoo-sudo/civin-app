<?php

namespace App\Features\Games\Plugins\Quiz\Http\Controllers;

use App\Features\Games\Plugins\Quiz\Actions\QuizAction;
use App\Features\Games\Plugins\Quiz\DTOs\JoinQuizDTO;
use App\Features\Games\Plugins\Quiz\DTOs\QuizDTO;
use App\Features\Games\Plugins\Quiz\DTOs\StartQuizDTO;
use App\Features\Games\Plugins\Quiz\DTOs\SubmitAnswerDTO;
use App\Features\Games\Plugins\Quiz\Http\Requests\CreateQuizRequest;
use App\Features\Games\Plugins\Quiz\Http\Requests\JoinQuizRequest;
use App\Features\Games\Plugins\Quiz\Http\Requests\QuizCategoriesRequest;
use App\Features\Games\Plugins\Quiz\Http\Requests\QuizConfigRequest;
use App\Features\Games\Plugins\Quiz\Http\Requests\QuizHistoryRequest;
use App\Features\Games\Plugins\Quiz\Http\Requests\QuizLeaderboardRequest;
use App\Features\Games\Plugins\Quiz\Http\Requests\QuizQuestionsRequest;
use App\Features\Games\Plugins\Quiz\Http\Requests\QuizStatisticsRequest;
use App\Features\Games\Plugins\Quiz\Http\Requests\QuizTournamentsRequest;
use App\Features\Games\Plugins\Quiz\Http\Requests\StartQuizRequest;
use App\Features\Games\Plugins\Quiz\Http\Requests\SubmitAnswerRequest;
use App\Features\Games\Plugins\Quiz\Http\Resources\QuizAnswerResource;
use App\Features\Games\Plugins\Quiz\Http\Resources\QuizPlayerResource;
use App\Features\Games\Plugins\Quiz\Http\Resources\QuizQuestionResource;
use App\Features\Games\Plugins\Quiz\Http\Resources\QuizSessionResource;
use App\Features\Games\Plugins\Quiz\Http\Resources\QuizTournamentResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class QuizController extends Controller
{
    public function config(QuizConfigRequest $request, QuizAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->config($request->user()),
        ]);
    }

    public function categories(QuizCategoriesRequest $request, QuizAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->categories($request->user())->values()->all(),
        ]);
    }

    public function questions(QuizQuestionsRequest $request, QuizAction $action): AnonymousResourceCollection
    {
        return QuizQuestionResource::collection(
            $action->questions(
                $request->user(),
                $request->validated('category_id'),
                $request->validated('difficulty'),
                $request->validated('language'),
                (int) ($request->validated('per_page') ?? 30),
            ),
        );
    }

    public function create(CreateQuizRequest $request, QuizAction $action): JsonResponse
    {
        $whitelist = $request->validated('whitelist_user_ids');

        $result = $action->create(
            $request->user(),
            new QuizDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                mode: (string) ($request->validated('mode') ?? 'classic'),
                categoryId: $request->validated('category_id'),
                difficulty: (string) ($request->validated('difficulty') ?? 'medium'),
                questionIds: $request->validated('question_ids'),
                questionCount: (int) ($request->validated('question_count') ?? 10),
                timePerQuestion: (int) ($request->validated('time_per_question') ?? 15),
                rewardType: (string) ($request->validated('reward_type') ?? 'coins'),
                rewardPool: (int) ($request->validated('reward_pool') ?? 0),
                betCoins: (int) ($request->validated('bet_coins') ?? 0),
                winnerCount: (int) ($request->validated('winner_count') ?? 3),
                visibility: (string) ($request->validated('visibility') ?? 'public'),
                eligibility: (string) ($request->validated('eligibility') ?? 'everyone'),
                whitelistUserIds: $whitelist === null ? null : array_map('strval', $whitelist),
                roomId: $request->validated('room_id'),
                roomType: $request->validated('room_type'),
                tournamentId: $request->validated('tournament_id'),
                language: (string) ($request->validated('language') ?? 'en'),
                teamSettings: $request->validated('team_settings') ?? [],
                clientSessionId: $request->validated('client_session_id'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'session' => (new QuizSessionResource($result['session']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function join(JoinQuizRequest $request, QuizAction $action): JsonResponse
    {
        $result = $action->join(
            $request->user(),
            new JoinQuizDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                sessionId: (string) $request->validated('session_id'),
                teamId: $request->validated('team_id'),
                teamName: $request->validated('team_name'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'session' => (new QuizSessionResource($result['session']))->resolve($request),
                'player' => (new QuizPlayerResource($result['player']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function start(StartQuizRequest $request, QuizAction $action): JsonResponse
    {
        $result = $action->start(
            $request->user(),
            new StartQuizDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                sessionId: (string) $request->validated('session_id'),
            ),
        );

        return response()->json([
            'data' => [
                'session' => (new QuizSessionResource($result['session']))->resolve($request),
                'question' => $result['question'],
                'replay' => $result['replay'],
            ],
        ]);
    }

    public function answer(SubmitAnswerRequest $request, QuizAction $action): JsonResponse
    {
        $result = $action->answer(
            $request->user(),
            new SubmitAnswerDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                sessionId: (string) $request->validated('session_id'),
                questionId: (string) $request->validated('question_id'),
                selectedIndex: $request->validated('selected_index'),
                selectedText: $request->validated('selected_text'),
                clientResponseMs: $request->validated('client_response_ms'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'session' => (new QuizSessionResource($result['session']))->resolve($request),
                'answer' => (new QuizAnswerResource($result['answer']))->resolve($request),
                'player' => (new QuizPlayerResource($result['player']))->resolve($request),
                'score' => $result['score'],
                'replay' => $result['replay'],
            ],
        ]);
    }

    public function history(QuizHistoryRequest $request, QuizAction $action): JsonResponse
    {
        $paginator = $action->history(
            $request->user(),
            (int) ($request->validated('per_page') ?? 30),
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function leaderboard(QuizLeaderboardRequest $request, QuizAction $action): JsonResponse
    {
        $entries = $action->leaderboard(
            (string) ($request->validated('period') ?? 'daily'),
            (int) ($request->validated('limit') ?? 50),
            $request->validated('category_id'),
            $request->validated('room_id'),
        );

        return response()->json([
            'data' => $entries->values()->all(),
        ]);
    }

    public function statistics(QuizStatisticsRequest $request, QuizAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->statistics($request->user()),
        ]);
    }

    public function tournaments(QuizTournamentsRequest $request, QuizAction $action): AnonymousResourceCollection
    {
        return QuizTournamentResource::collection(
            $action->tournaments(
                $request->user(),
                (int) ($request->validated('per_page') ?? 30),
            ),
        );
    }
}
