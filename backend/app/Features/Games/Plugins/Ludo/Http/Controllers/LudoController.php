<?php

namespace App\Features\Games\Plugins\Ludo\Http\Controllers;

use App\Features\Games\Plugins\Ludo\Actions\LudoAction;
use App\Features\Games\Plugins\Ludo\DTOs\CreateLudoMatchDTO;
use App\Features\Games\Plugins\Ludo\DTOs\JoinLudoMatchDTO;
use App\Features\Games\Plugins\Ludo\DTOs\JoinLudoTournamentDTO;
use App\Features\Games\Plugins\Ludo\DTOs\LeaveLudoDTO;
use App\Features\Games\Plugins\Ludo\DTOs\MovePieceDTO;
use App\Features\Games\Plugins\Ludo\DTOs\ReadyLudoDTO;
use App\Features\Games\Plugins\Ludo\DTOs\ReconnectLudoDTO;
use App\Features\Games\Plugins\Ludo\DTOs\RollDiceDTO;
use App\Features\Games\Plugins\Ludo\Http\Requests\CreateLudoMatchRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\JoinLudoMatchRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\JoinLudoTournamentRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\LeaveLudoRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\LudoConfigRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\LudoHistoryRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\LudoLeaderboardRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\LudoStatisticsRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\LudoTournamentsRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\MovePieceRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\ReadyLudoRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\ReconnectLudoRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\RollDiceRequest;
use App\Features\Games\Plugins\Ludo\Http\Requests\ShowLudoMatchRequest;
use App\Features\Games\Plugins\Ludo\Http\Resources\LudoMatchResource;
use App\Features\Games\Plugins\Ludo\Http\Resources\LudoMoveResource;
use App\Features\Games\Plugins\Ludo\Http\Resources\LudoReplayResource;
use App\Features\Games\Plugins\Ludo\Http\Resources\LudoTournamentResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LudoController extends Controller
{
    public function config(LudoConfigRequest $request, LudoAction $action): JsonResponse
    {
        return response()->json(['data' => $action->config($request->user())]);
    }

    public function create(CreateLudoMatchRequest $request, LudoAction $action): JsonResponse
    {
        $result = $action->create($request->user(), new CreateLudoMatchDTO(
            idempotencyKey: $request->string('idempotency_key')->toString(),
            mode: $request->string('mode', 'classic_4')->toString(),
            matchmaking: $request->string('matchmaking', 'private')->toString(),
            entryFeeCoins: $request->integer('entry_fee_coins'),
            maxPlayers: $request->integer('max_players', 4),
            roomId: $request->validated('room_id'), roomType: $request->validated('room_type'),
            tournamentId: $request->validated('tournament_id'), metadata: $request->validated('metadata') ?? [],
        ));

        return response()->json(['data' => [
            'match' => (new LudoMatchResource($result['match']))->resolve($request),
            'wallet' => (new WalletResource($result['wallet']))->resolve($request),
            'replay' => $result['replay'],
        ]], $result['replay'] ? 200 : 201);
    }

    public function join(JoinLudoMatchRequest $request, LudoAction $action): JsonResponse
    {
        $result = $action->join($request->user(), new JoinLudoMatchDTO(
            $request->string('idempotency_key')->toString(), $request->string('match_id')->toString(),
            $request->validated('invite_code'), $request->boolean('spectator'), $request->validated('metadata') ?? [],
        ));

        return response()->json(['data' => [
            'match' => (new LudoMatchResource($result['match']))->resolve($request),
            'wallet' => (new WalletResource($result['wallet']))->resolve($request),
            'replay' => $result['replay'],
        ]], $result['replay'] ? 200 : 201);
    }

    public function ready(ReadyLudoRequest $request, LudoAction $action): JsonResponse
    {
        $match = $action->ready($request->user(), new ReadyLudoDTO($request->string('idempotency_key')->toString(), $request->string('match_id')->toString(), $request->boolean('ready', true)));

        return response()->json(['data' => new LudoMatchResource($match)]);
    }

    public function roll(RollDiceRequest $request, LudoAction $action): JsonResponse
    {
        $result = $action->roll($request->user(), new RollDiceDTO($request->string('idempotency_key')->toString(), $request->string('match_id')->toString()));

        return response()->json(['data' => ['match' => new LudoMatchResource($result['match']), 'dice' => $result['dice'], 'legal_moves' => $result['legal_moves'], 'replay' => $result['replay']]]);
    }

    public function move(MovePieceRequest $request, LudoAction $action): JsonResponse
    {
        $result = $action->move($request->user(), new MovePieceDTO($request->string('idempotency_key')->toString(), $request->string('match_id')->toString(), $request->integer('piece_index'), $request->validated('metadata') ?? []));

        return response()->json(['data' => ['match' => new LudoMatchResource($result['match']), 'move' => new LudoMoveResource($result['move']), 'replay' => $result['replay']]]);
    }

    public function reconnect(ReconnectLudoRequest $request, LudoAction $action): JsonResponse
    {
        return response()->json(['data' => new LudoMatchResource($action->reconnect($request->user(), new ReconnectLudoDTO($request->string('idempotency_key')->toString(), $request->string('match_id')->toString())))]);
    }

    public function leave(LeaveLudoRequest $request, LudoAction $action): JsonResponse
    {
        return response()->json(['data' => new LudoMatchResource($action->leave($request->user(), new LeaveLudoDTO($request->string('idempotency_key')->toString(), $request->string('match_id')->toString())))]);
    }

    public function show(ShowLudoMatchRequest $request, LudoAction $action, string $match): JsonResponse
    {
        return response()->json(['data' => new LudoMatchResource($action->show($request->user(), $match))]);
    }

    public function history(LudoHistoryRequest $request, LudoAction $action): JsonResponse
    {
        return $this->paginated($action->history($request->user(), $request->integer('per_page', 30)));
    }

    public function leaderboard(LudoLeaderboardRequest $request, LudoAction $action): JsonResponse
    {
        return response()->json(['data' => $action->leaderboard($request->string('period', 'daily')->toString(), $request->integer('limit', 50), $request->validated('mode'))->all()]);
    }

    public function statistics(LudoStatisticsRequest $request, LudoAction $action): JsonResponse
    {
        return response()->json(['data' => $action->statistics($request->user())]);
    }

    public function tournaments(LudoTournamentsRequest $request, LudoAction $action): JsonResponse
    {
        return $this->paginated($action->tournaments($request->integer('per_page', 30)), LudoTournamentResource::class);
    }

    public function replays(Request $request, LudoAction $action): JsonResponse
    {
        return $this->paginated($action->replays($request->user(), min(100, max(1, $request->integer('per_page', 30)))), LudoReplayResource::class);
    }

    public function joinTournament(JoinLudoTournamentRequest $request, LudoAction $action): JsonResponse
    {
        $tournament = $action->joinTournament($request->user(), new JoinLudoTournamentDTO($request->string('idempotency_key')->toString(), $request->string('tournament_id')->toString(), $request->validated('metadata') ?? []));

        return response()->json(['data' => new LudoTournamentResource($tournament)], 201);
    }

    private function paginated($paginator, ?string $resource = null): JsonResponse
    {
        $items = $resource ? $resource::collection($paginator->items())->resolve() : $paginator->items();

        return response()->json(['data' => $items, 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()]]);
    }
}
