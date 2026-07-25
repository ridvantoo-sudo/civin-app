<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Controllers;

use App\Features\Games\Plugins\RockPaperScissors\Actions\RpsAction;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\AcceptRpsMatchDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\CreateRpsMatchDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\JoinRpsTournamentDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\RematchRpsDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\SubmitRpsMoveDTO;
use App\Features\Games\Plugins\RockPaperScissors\Http\Requests\AcceptRpsMatchRequest;
use App\Features\Games\Plugins\RockPaperScissors\Http\Requests\CreateRpsMatchRequest;
use App\Features\Games\Plugins\RockPaperScissors\Http\Requests\JoinRpsTournamentRequest;
use App\Features\Games\Plugins\RockPaperScissors\Http\Requests\RematchRpsRequest;
use App\Features\Games\Plugins\RockPaperScissors\Http\Requests\RpsConfigRequest;
use App\Features\Games\Plugins\RockPaperScissors\Http\Requests\RpsHistoryRequest;
use App\Features\Games\Plugins\RockPaperScissors\Http\Requests\RpsLeaderboardRequest;
use App\Features\Games\Plugins\RockPaperScissors\Http\Requests\RpsStatisticsRequest;
use App\Features\Games\Plugins\RockPaperScissors\Http\Requests\RpsTournamentsRequest;
use App\Features\Games\Plugins\RockPaperScissors\Http\Requests\ShowRpsMatchRequest;
use App\Features\Games\Plugins\RockPaperScissors\Http\Requests\SubmitRpsMoveRequest;
use App\Features\Games\Plugins\RockPaperScissors\Http\Resources\RpsMatchResource;
use App\Features\Games\Plugins\RockPaperScissors\Http\Resources\RpsMoveResource;
use App\Features\Games\Plugins\RockPaperScissors\Http\Resources\RpsRoundResource;
use App\Features\Games\Plugins\RockPaperScissors\Http\Resources\RpsTournamentResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class RockPaperScissorsController extends Controller
{
    public function config(RpsConfigRequest $request, RpsAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->config($request->user()),
        ]);
    }

    public function create(CreateRpsMatchRequest $request, RpsAction $action): JsonResponse
    {
        $whitelist = $request->validated('whitelist_user_ids');

        $result = $action->create(
            $request->user(),
            new CreateRpsMatchDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                mode: (string) ($request->validated('mode') ?? 'classic_1v1'),
                matchmaking: (string) ($request->validated('matchmaking') ?? 'private'),
                opponentId: $request->validated('opponent_id'),
                entryFeeCoins: (int) ($request->validated('entry_fee_coins') ?? 0),
                rewardType: (string) ($request->validated('reward_type') ?? 'coins'),
                visibility: (string) ($request->validated('visibility') ?? 'private'),
                eligibility: (string) ($request->validated('eligibility') ?? 'everyone'),
                whitelistUserIds: $whitelist === null ? null : array_map('strval', $whitelist),
                roundTimeoutSeconds: $request->validated('round_timeout_seconds'),
                roomId: $request->validated('room_id'),
                roomType: $request->validated('room_type'),
                tournamentId: $request->validated('tournament_id'),
                clientMatchId: $request->validated('client_match_id'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'match' => (new RpsMatchResource($result['match']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'matched' => $result['matched'],
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function accept(AcceptRpsMatchRequest $request, RpsAction $action): JsonResponse
    {
        $result = $action->accept(
            $request->user(),
            new AcceptRpsMatchDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                matchId: (string) $request->validated('match_id'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'match' => (new RpsMatchResource($result['match']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function move(SubmitRpsMoveRequest $request, RpsAction $action): JsonResponse
    {
        $result = $action->move(
            $request->user(),
            new SubmitRpsMoveDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                matchId: (string) $request->validated('match_id'),
                move: (string) $request->validated('move'),
                roundId: $request->validated('round_id'),
                clientResponseMs: $request->validated('client_response_ms'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'match' => (new RpsMatchResource($result['match']))->resolve($request),
                'round' => (new RpsRoundResource($result['round']))->resolve($request),
                'move' => (new RpsMoveResource($result['move']))->resolve($request),
                'resolved' => $result['resolved'],
                'finished' => $result['finished'],
                'replay' => $result['replay'],
            ],
        ]);
    }

    public function rematch(RematchRpsRequest $request, RpsAction $action): JsonResponse
    {
        $result = $action->rematch(
            $request->user(),
            new RematchRpsDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                matchId: (string) $request->validated('match_id'),
                entryFeeCoins: $request->validated('entry_fee_coins'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'match' => (new RpsMatchResource($result['match']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function joinTournament(JoinRpsTournamentRequest $request, RpsAction $action): JsonResponse
    {
        $result = $action->joinTournament(
            $request->user(),
            new JoinRpsTournamentDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                tournamentId: (string) $request->validated('tournament_id'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'tournament' => (new RpsTournamentResource($result['tournament']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function show(ShowRpsMatchRequest $request, RpsAction $action, string $match): JsonResponse
    {
        return response()->json([
            'data' => (new RpsMatchResource($action->show($request->user(), $match)))->resolve($request),
        ]);
    }

    public function history(RpsHistoryRequest $request, RpsAction $action): JsonResponse
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

    public function leaderboard(RpsLeaderboardRequest $request, RpsAction $action): JsonResponse
    {
        $entries = $action->leaderboard(
            (string) ($request->validated('period') ?? 'daily'),
            (int) ($request->validated('limit') ?? 50),
            $request->validated('mode'),
            $request->validated('room_id'),
        );

        return response()->json([
            'data' => $entries->values()->all(),
        ]);
    }

    public function statistics(RpsStatisticsRequest $request, RpsAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->statistics($request->user()),
        ]);
    }

    public function tournaments(RpsTournamentsRequest $request, RpsAction $action): AnonymousResourceCollection
    {
        return RpsTournamentResource::collection(
            $action->tournaments(
                $request->user(),
                (int) ($request->validated('per_page') ?? 30),
            ),
        );
    }
}
