<?php

namespace App\Features\Games\Plugins\RedEnvelope\Http\Controllers;

use App\Features\Games\Plugins\RedEnvelope\Actions\RedEnvelopeAction;
use App\Features\Games\Plugins\RedEnvelope\DTOs\ClaimRedEnvelopeDTO;
use App\Features\Games\Plugins\RedEnvelope\DTOs\CreateRedEnvelopeDTO;
use App\Features\Games\Plugins\RedEnvelope\Http\Requests\ClaimRedEnvelopeRequest;
use App\Features\Games\Plugins\RedEnvelope\Http\Requests\CreateRedEnvelopeRequest;
use App\Features\Games\Plugins\RedEnvelope\Http\Requests\RedEnvelopeConfigRequest;
use App\Features\Games\Plugins\RedEnvelope\Http\Requests\RedEnvelopeHistoryRequest;
use App\Features\Games\Plugins\RedEnvelope\Http\Requests\RedEnvelopeLeaderboardRequest;
use App\Features\Games\Plugins\RedEnvelope\Http\Resources\RedEnvelopeClaimResource;
use App\Features\Games\Plugins\RedEnvelope\Http\Resources\RedEnvelopeResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class RedEnvelopeController extends Controller
{
    public function config(RedEnvelopeConfigRequest $request, RedEnvelopeAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->config($request->user()),
        ]);
    }

    public function create(CreateRedEnvelopeRequest $request, RedEnvelopeAction $action): JsonResponse
    {
        $whitelist = $request->validated('whitelist_user_ids');

        $result = $action->create(
            $request->user(),
            new CreateRedEnvelopeDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                rewardType: (string) $request->validated('reward_type'),
                distributionType: (string) $request->validated('distribution_type'),
                visibility: (string) $request->validated('visibility'),
                claimScope: (string) $request->validated('claim_scope'),
                totalAmount: (int) $request->validated('total_amount'),
                winnerCount: (int) $request->validated('winner_count'),
                durationSeconds: (int) $request->validated('duration_seconds'),
                roomType: $request->validated('room_type'),
                roomId: $request->validated('room_id'),
                whitelistUserIds: $whitelist === null ? null : array_map('strval', $whitelist),
                message: $request->validated('message'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'envelope' => (new RedEnvelopeResource($result['envelope']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function claim(ClaimRedEnvelopeRequest $request, RedEnvelopeAction $action): JsonResponse
    {
        $result = $action->claim(
            $request->user(),
            new ClaimRedEnvelopeDTO(
                idempotencyKey: (string) $request->validated('idempotency_key'),
                envelopeId: (string) $request->validated('envelope_id'),
                metadata: $request->validated('metadata') ?? [],
            ),
        );

        return response()->json([
            'data' => [
                'envelope' => (new RedEnvelopeResource($result['envelope']))->resolve($request),
                'claim' => (new RedEnvelopeClaimResource($result['claim']))->resolve($request),
                'wallet' => (new WalletResource($result['wallet']))->resolve($request),
                'replay' => $result['replay'],
            ],
        ], $result['replay'] ? 200 : 201);
    }

    public function history(RedEnvelopeHistoryRequest $request, RedEnvelopeAction $action): AnonymousResourceCollection
    {
        return RedEnvelopeResource::collection(
            $action->history(
                $request->user(),
                (string) ($request->validated('type') ?? 'all'),
                (int) ($request->validated('per_page') ?? 30),
            ),
        );
    }

    public function leaderboard(RedEnvelopeLeaderboardRequest $request, RedEnvelopeAction $action): JsonResponse
    {
        $entries = $action->leaderboard(
            (string) ($request->validated('period') ?? 'daily'),
            (string) ($request->validated('type') ?? 'winners'),
            (int) ($request->validated('limit') ?? 50),
        );

        return response()->json([
            'data' => $entries->values()->all(),
        ]);
    }
}
