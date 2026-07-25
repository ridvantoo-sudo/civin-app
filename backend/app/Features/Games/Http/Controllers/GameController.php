<?php

namespace App\Features\Games\Http\Controllers;

use App\Features\Games\Actions\CreateGameMatch;
use App\Features\Games\Actions\GetGame;
use App\Features\Games\Actions\GetGameLeaderboard;
use App\Features\Games\Actions\GetGameMatch;
use App\Features\Games\Actions\GetGameStats;
use App\Features\Games\Actions\GetLotteryRound;
use App\Features\Games\Actions\GetQuizQuestion;
use App\Features\Games\Actions\JoinGameMatch;
use App\Features\Games\Actions\ListGameHistory;
use App\Features\Games\Actions\ListGames;
use App\Features\Games\Actions\PerformMatchAction;
use App\Features\Games\Actions\PlayGame;
use App\Features\Games\Actions\TrackGameAnalytics;
use App\Features\Games\DTOs\CreateMatchData;
use App\Features\Games\DTOs\MatchActionData;
use App\Features\Games\DTOs\PlayGameData;
use App\Features\Games\DTOs\TrackAnalyticsData;
use App\Features\Games\Http\Requests\CreateMatchRequest;
use App\Features\Games\Http\Requests\LeaderboardRequest;
use App\Features\Games\Http\Requests\MatchActionRequest;
use App\Features\Games\Http\Requests\PlayGameRequest;
use App\Features\Games\Http\Requests\TrackAnalyticsRequest;
use App\Features\Games\Http\Resources\GameMatchResource;
use App\Features\Games\Http\Resources\GamePlayResource;
use App\Features\Games\Http\Resources\GameResource;
use App\Features\Games\Http\Resources\LeaderboardEntryResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class GameController extends Controller
{
    public function index(ListGames $action): AnonymousResourceCollection
    {
        return GameResource::collection($action->execute());
    }

    public function show(string $slug, GetGame $action): JsonResponse
    {
        return response()->json([
            'data' => (new GameResource($action->execute($slug)))->resolve(),
        ]);
    }

    public function play(PlayGameRequest $request, PlayGame $action): JsonResponse
    {
        $result = $action->execute(
            $request->user(),
            new PlayGameData(
                gameSlug: (string) $request->validated('game_slug'),
                betCoins: (int) ($request->validated('bet_coins') ?? 0),
                input: $request->validated('input') ?? [],
                metadata: $request->validated('metadata'),
            ),
        );

        return response()->json([
            'data' => [
                'play' => (new GamePlayResource($result->play))->resolve($request),
                'outcome' => $result->outcome,
                'wallet' => (new WalletResource($result->wallet))->resolve($request),
            ],
        ], 201);
    }

    public function history(Request $request, ListGameHistory $action): AnonymousResourceCollection
    {
        $gameSlug = $request->query('game_slug');
        $gameSlug = is_string($gameSlug) && $gameSlug !== '' ? $gameSlug : null;

        return GamePlayResource::collection(
            $action->execute(
                $request->user(),
                $gameSlug,
                (int) $request->integer('per_page', 30),
            ),
        );
    }

    public function leaderboard(LeaderboardRequest $request, GetGameLeaderboard $action): AnonymousResourceCollection
    {
        $gameSlug = $request->validated('game_slug');
        $gameSlug = is_string($gameSlug) && $gameSlug !== '' ? $gameSlug : null;

        return LeaderboardEntryResource::collection(
            $action->execute(
                $gameSlug,
                (string) ($request->validated('period') ?? 'daily'),
                (int) ($request->validated('limit') ?? 50),
            ),
        );
    }

    public function stats(Request $request, GetGameStats $action): JsonResponse
    {
        $gameSlug = $request->query('game_slug');
        $gameSlug = is_string($gameSlug) && $gameSlug !== '' ? $gameSlug : null;

        return response()->json([
            'data' => $action->execute($request->user(), $gameSlug),
        ]);
    }

    public function analytics(TrackAnalyticsRequest $request, TrackGameAnalytics $action): JsonResponse
    {
        $action->execute(
            $request->user(),
            new TrackAnalyticsData(
                event: (string) $request->validated('event'),
                gameSlug: $request->validated('game_slug'),
                payload: $request->validated('payload'),
            ),
        );

        return response()->json([
            'data' => ['tracked' => true],
        ], 201);
    }

    public function quizQuestion(GetQuizQuestion $action): JsonResponse
    {
        return response()->json([
            'data' => $action->execute(),
        ]);
    }

    public function lotteryCurrent(GetLotteryRound $action): JsonResponse
    {
        $round = $action->execute();

        return response()->json([
            'data' => [
                'id' => $round->id,
                'game_id' => $round->game_id,
                'ticket_price' => $round->ticket_price,
                'max_number' => $round->max_number,
                'picks' => $round->picks,
                'winning_numbers' => $round->winning_numbers,
                'status' => $round->status,
                'closes_at' => $round->closes_at?->toIso8601String(),
                'drawn_at' => $round->drawn_at?->toIso8601String(),
            ],
        ]);
    }

    public function createMatch(CreateMatchRequest $request, CreateGameMatch $action): JsonResponse
    {
        $match = $action->execute(
            $request->user(),
            new CreateMatchData(
                gameSlug: (string) $request->validated('game_slug'),
                stakeCoins: (int) ($request->validated('stake_coins') ?? 0),
                maxPlayers: (int) ($request->validated('max_players') ?? 2),
                metadata: $request->validated('metadata'),
            ),
        );

        return response()->json([
            'data' => (new GameMatchResource($match))->resolve($request),
        ], 201);
    }

    public function showMatch(string $match, GetGameMatch $action): JsonResponse
    {
        return response()->json([
            'data' => (new GameMatchResource($action->execute($match)))->resolve(),
        ]);
    }

    public function joinMatch(Request $request, string $match, JoinGameMatch $action): JsonResponse
    {
        return response()->json([
            'data' => (new GameMatchResource(
                $action->execute($request->user(), $match),
            ))->resolve($request),
        ]);
    }

    public function matchAction(
        MatchActionRequest $request,
        string $match,
        PerformMatchAction $action,
    ): JsonResponse {
        $updated = $action->execute(
            $request->user(),
            $match,
            new MatchActionData(
                action: (string) $request->validated('action'),
                payload: $request->validated('payload') ?? [],
            ),
        );

        return response()->json([
            'data' => (new GameMatchResource($updated))->resolve($request),
        ]);
    }
}
