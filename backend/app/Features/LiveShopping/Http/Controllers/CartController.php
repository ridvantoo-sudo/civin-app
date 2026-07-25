<?php

namespace App\Features\LiveShopping\Http\Controllers;

use App\Features\LiveShopping\Actions\ManageCartAndOrders;
use App\Features\LiveShopping\DTOs\CartItemData;
use App\Features\LiveShopping\Http\Requests\CartItemRequest;
use App\Features\LiveShopping\Http\Requests\UpdateCartItemRequest;
use App\Features\LiveShopping\Http\Resources\ShoppingCartResource;
use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Models\ShoppingCartItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CartController extends Controller
{
    public function show(Request $request, ManageCartAndOrders $action): JsonResponse
    {
        $this->authorize('checkout', ShopOrder::class);

        $cart = $action->cart($request->user());

        return response()->json([
            'data' => (new ShoppingCartResource($cart))->resolve($request),
        ]);
    }

    public function addItem(CartItemRequest $request, ManageCartAndOrders $action): JsonResponse
    {
        $this->authorize('checkout', ShopOrder::class);

        $validated = $request->validated();
        $cart = $action->addItem(
            $request->user(),
            new CartItemData(
                productId: (string) $validated['product_id'],
                quantity: (int) ($validated['quantity'] ?? 1),
                livePinId: $validated['live_pin_id'] ?? null,
                roomId: $validated['room_id'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new ShoppingCartResource($cart))->resolve($request),
        ], 201);
    }

    public function updateItem(
        UpdateCartItemRequest $request,
        ShoppingCartItem $item,
        ManageCartAndOrders $action,
    ): JsonResponse {
        $this->authorize('checkout', ShopOrder::class);

        $cart = $action->updateItem(
            $request->user(),
            (string) $item->getKey(),
            (int) $request->validated('quantity'),
        );

        return response()->json([
            'data' => (new ShoppingCartResource($cart))->resolve($request),
        ]);
    }

    public function removeItem(Request $request, ShoppingCartItem $item, ManageCartAndOrders $action): JsonResponse
    {
        $this->authorize('checkout', ShopOrder::class);

        $cart = $action->removeItem($request->user(), (string) $item->getKey());

        return response()->json([
            'data' => (new ShoppingCartResource($cart))->resolve($request),
        ]);
    }

    public function clear(Request $request, ManageCartAndOrders $action): JsonResponse
    {
        $this->authorize('checkout', ShopOrder::class);

        $cart = $action->clear($request->user());

        return response()->json([
            'data' => (new ShoppingCartResource($cart))->resolve($request),
        ]);
    }
}
