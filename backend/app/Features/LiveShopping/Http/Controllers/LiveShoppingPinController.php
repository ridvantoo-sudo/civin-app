<?php

namespace App\Features\LiveShopping\Http\Controllers;

use App\Features\LiveShopping\Actions\ManageLivePins;
use App\Features\LiveShopping\DTOs\PinProductData;
use App\Features\LiveShopping\Http\Requests\PinProductRequest;
use App\Features\LiveShopping\Http\Resources\LiveProductPinResource;
use App\Features\LiveShopping\Models\LiveProductPin;
use App\Features\LiveShopping\Models\StoreProduct;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LiveShoppingPinController extends Controller
{
    public function index(LiveRoom $room, ManageLivePins $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StoreProduct::class);

        return LiveProductPinResource::collection($action->list($room));
    }

    public function store(PinProductRequest $request, LiveRoom $room, ManageLivePins $action): JsonResponse
    {
        $this->authorize('create', StoreProduct::class);

        $validated = $request->validated();
        $pin = $action->pin(
            $request->user(),
            $room,
            new PinProductData(
                productId: (string) $validated['product_id'],
                isPinned: (bool) ($validated['is_pinned'] ?? true),
                flashDealPrice: isset($validated['flash_deal_price']) ? (int) $validated['flash_deal_price'] : null,
                flashDealEndsAt: $validated['flash_deal_ends_at'] ?? null,
                sortOrder: (int) ($validated['sort_order'] ?? 0),
            ),
        );

        return response()->json([
            'data' => (new LiveProductPinResource($pin))->resolve($request),
        ], 201);
    }

    public function update(
        PinProductRequest $request,
        LiveRoom $room,
        LiveProductPin $pin,
        ManageLivePins $action,
    ): JsonResponse {
        $this->authorize('create', StoreProduct::class);

        $validated = $request->validated();
        $pin = $action->update(
            $request->user(),
            $room,
            $pin,
            new PinProductData(
                productId: (string) ($validated['product_id'] ?? $pin->product_id),
                isPinned: (bool) ($validated['is_pinned'] ?? $pin->is_pinned),
                flashDealPrice: array_key_exists('flash_deal_price', $validated)
                    ? ($validated['flash_deal_price'] === null ? null : (int) $validated['flash_deal_price'])
                    : $pin->flash_deal_price,
                flashDealEndsAt: array_key_exists('flash_deal_ends_at', $validated)
                    ? $validated['flash_deal_ends_at']
                    : $pin->flash_deal_ends_at?->toDateTimeString(),
                sortOrder: (int) ($validated['sort_order'] ?? $pin->sort_order),
            ),
        );

        return response()->json([
            'data' => (new LiveProductPinResource($pin))->resolve($request),
        ]);
    }

    public function destroy(Request $request, LiveRoom $room, LiveProductPin $pin, ManageLivePins $action): JsonResponse
    {
        $this->authorize('create', StoreProduct::class);

        $pin = $action->unpin($request->user(), $room, $pin);

        return response()->json([
            'data' => (new LiveProductPinResource($pin))->resolve($request),
        ]);
    }
}
