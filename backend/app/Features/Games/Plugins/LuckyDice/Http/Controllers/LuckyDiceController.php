<?php

namespace App\Features\Games\Plugins\LuckyDice\Http\Controllers;

use App\Features\Games\Plugins\LuckyDice\Actions\LuckyDiceAction;
use App\Features\Games\Plugins\LuckyDice\DTOs\LuckyDiceDTO;
use App\Features\Games\Plugins\LuckyDice\Http\Requests\LuckyDiceConfigRequest;
use App\Features\Games\Plugins\LuckyDice\Http\Requests\LuckyDiceHistoryRequest;
use App\Features\Games\Plugins\LuckyDice\Http\Requests\LuckyDiceLeaderboardRequest;
use App\Features\Games\Plugins\LuckyDice\Http\Requests\PlayLuckyDiceRequest;
use App\Features\Games\Plugins\LuckyDice\Http\Resources\LuckyDiceResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LuckyDiceController extends Controller
{
    public function config(LuckyDiceConfigRequest $request, LuckyDiceAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->config($request->user()),
        ]);
    }

    public function play(PlayLuckyDiceRequest $request, LuckyDiceAction $action): JsonResponse
    {
        $result = $action->play(
            $request->user(),
            new LuckyDiceDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                betMode: (string) $request->validated('bet_mode'),
                prediction: (string) $request->validated('prediction'),
                betAmount: (int) $request->validated('bet_amount'),
                clientSessionId: $request->validated('client_session_id'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'bet' => (new LuckyDiceResource($result['bet']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function history(LuckyDiceHistoryRequest $request, LuckyDiceAction $action): AnonymousResourceCollection
    {
        return LuckyDiceResource::collection(
            $action->history(
                $request->user(),
                (int) ($request->validated('per_page') ?? 30),
            ),
        );
    }

    public function leaderboard(LuckyDiceLeaderboardRequest $request, LuckyDiceAction $action): JsonResponse
    {
        $entries = $action->leaderboard(
            (string) ($request->validated('period') ?? 'daily'),
            (int) ($request->validated('limit') ?? 50),
            (string) ($request->validated('type') ?? 'winners'),
        );

        return response()->json([
            'data' => $entries->values()->all(),
        ]);
    }
}
