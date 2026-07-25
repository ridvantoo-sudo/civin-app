<?php

namespace App\Features\LiveShopping\Http\Controllers;

use App\Features\LiveShopping\Actions\ManageCartAndOrders;
use App\Features\LiveShopping\DTOs\CheckoutData;
use App\Features\LiveShopping\DTOs\UpdateShippingData;
use App\Features\LiveShopping\Http\Requests\CheckoutRequest;
use App\Features\LiveShopping\Http\Requests\CompleteExternalPaymentRequest;
use App\Features\LiveShopping\Http\Requests\UpdateShippingRequest;
use App\Features\LiveShopping\Http\Resources\ShopOrderResource;
use App\Features\LiveShopping\Models\ShopOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrderController extends Controller
{
    public function checkout(CheckoutRequest $request, ManageCartAndOrders $action): JsonResponse
    {
        $this->authorize('checkout', ShopOrder::class);

        $validated = $request->validated();
        $order = $action->checkout(
            $request->user(),
            new CheckoutData(
                paymentMethod: (string) $validated['payment_method'],
                roomId: $validated['room_id'] ?? null,
                shippingName: $validated['shipping_name'] ?? null,
                shippingPhone: $validated['shipping_phone'] ?? null,
                shippingAddressLine: $validated['shipping_address_line'] ?? null,
                shippingCity: $validated['shipping_city'] ?? null,
                shippingCountry: $validated['shipping_country'] ?? null,
                shippingPostalCode: $validated['shipping_postal_code'] ?? null,
                paymentProvider: $validated['payment_provider'] ?? null,
                paymentTransactionId: $validated['payment_transaction_id'] ?? null,
                items: $validated['items'] ?? null,
                clearCart: (bool) ($validated['clear_cart'] ?? true),
                metadata: $validated['metadata'] ?? null,
                couponCode: isset($validated['coupon_code']) ? (string) $validated['coupon_code'] : null,
            ),
        );

        return response()->json([
            'data' => (new ShopOrderResource($order))->resolve($request),
        ], 201);
    }

    public function index(Request $request, ManageCartAndOrders $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ShopOrder::class);

        return ShopOrderResource::collection(
            $action->buyerOrders($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function sellerIndex(Request $request, ManageCartAndOrders $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ShopOrder::class);

        return ShopOrderResource::collection(
            $action->sellerOrders($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function show(Request $request, ShopOrder $order, ManageCartAndOrders $action): JsonResponse
    {
        $this->authorize('view', $order);

        $order = $action->show($request->user(), $order);

        return response()->json([
            'data' => (new ShopOrderResource($order))->resolve($request),
        ]);
    }

    public function pay(
        CompleteExternalPaymentRequest $request,
        ShopOrder $order,
        ManageCartAndOrders $action,
    ): JsonResponse {
        $this->authorize('pay', $order);

        $validated = $request->validated();
        $order = $action->payExternal(
            $request->user(),
            $order,
            (string) $validated['payment_provider'],
            (string) $validated['payment_transaction_id'],
        );

        return response()->json([
            'data' => (new ShopOrderResource($order))->resolve($request),
        ]);
    }

    public function updateShipping(
        UpdateShippingRequest $request,
        ShopOrder $order,
        ManageCartAndOrders $action,
    ): JsonResponse {
        $this->authorize('updateShipping', $order);

        $validated = $request->validated();
        $order = $action->updateShipping(
            $request->user(),
            $order,
            new UpdateShippingData(
                status: (string) $validated['status'],
                trackingNumber: $validated['tracking_number'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new ShopOrderResource($order))->resolve($request),
        ]);
    }
}
