<?php

namespace App\Features\Gifts\Http\Controllers;

use App\Features\Gifts\Actions\ListGiftHistory;
use App\Features\Gifts\Actions\ListGifts;
use App\Features\Gifts\Actions\SendGift;
use App\Features\Gifts\DTOs\SendGiftData;
use App\Features\Gifts\Http\Requests\ListGiftHistoryRequest;
use App\Features\Gifts\Http\Requests\SendGiftRequest;
use App\Features\Gifts\Http\Resources\GiftResource;
use App\Features\Gifts\Http\Resources\GiftTransactionResource;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class GiftController extends Controller
{
    public function index(ListGifts $action): AnonymousResourceCollection
    {
        return GiftResource::collection($action->execute());
    }

    public function send(
        SendGiftRequest $request,
        LiveRoom $room,
        SendGift $action,
    ): JsonResponse {
        $this->authorize('sendGift', $room);

        $transaction = $action->execute(
            $room,
            $request->user(),
            new SendGiftData(
                giftId: (string) $request->validated('gift_id'),
                quantity: (int) ($request->validated('quantity') ?? 1),
                metadata: $request->validated('metadata'),
                clientRequestId: $request->validated('client_request_id'),
            ),
        );

        return response()->json([
            'data' => (new GiftTransactionResource($transaction))->resolve($request),
        ], 201);
    }

    public function history(
        ListGiftHistoryRequest $request,
        User $user,
        ListGiftHistory $action,
    ): AnonymousResourceCollection {
        $this->authorize('viewGiftHistory', $user);

        return GiftTransactionResource::collection(
            $action->execute($request->user(), $user, $request->perPage()),
        );
    }
}
