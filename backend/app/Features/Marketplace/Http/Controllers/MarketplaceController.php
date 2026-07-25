<?php

namespace App\Features\Marketplace\Http\Controllers;

use App\Features\Marketplace\Actions\EquipMarketItem;
use App\Features\Marketplace\Actions\GetEquippedMarketItems;
use App\Features\Marketplace\Actions\GetMarketplaceInventory;
use App\Features\Marketplace\Actions\GetMarketplacePurchases;
use App\Features\Marketplace\Actions\ListMarketItems;
use App\Features\Marketplace\Actions\ListStickerPackItems;
use App\Features\Marketplace\Actions\PurchaseMarketItem;
use App\Features\Marketplace\Actions\UnequipMarketItem;
use App\Features\Marketplace\DTOs\EquipMarketItemData;
use App\Features\Marketplace\DTOs\PurchaseMarketItemData;
use App\Features\Marketplace\Http\Requests\EquipMarketItemRequest;
use App\Features\Marketplace\Http\Requests\ListMarketItemsRequest;
use App\Features\Marketplace\Http\Requests\ListMarketplacePurchasesRequest;
use App\Features\Marketplace\Http\Requests\PurchaseMarketItemRequest;
use App\Features\Marketplace\Http\Resources\MarketItemResource;
use App\Features\Marketplace\Http\Resources\MarketPurchaseResource;
use App\Features\Marketplace\Http\Resources\StickerPackItemResource;
use App\Features\Marketplace\Http\Resources\UserMarketItemResource;
use App\Features\Marketplace\Models\MarketItem;
use App\Features\Marketplace\Models\UserMarketItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MarketplaceController extends Controller
{
    public function items(ListMarketItemsRequest $request, ListMarketItems $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', UserMarketItem::class);

        return MarketItemResource::collection(
            $action->execute($request->validated('type')),
        );
    }

    public function stickers(
        Request $request,
        MarketItem $item,
        ListStickerPackItems $action,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', UserMarketItem::class);

        return StickerPackItemResource::collection($action->execute($item));
    }

    public function inventory(Request $request, GetMarketplaceInventory $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', UserMarketItem::class);

        return UserMarketItemResource::collection($action->execute($request->user()));
    }

    public function purchases(
        ListMarketplacePurchasesRequest $request,
        GetMarketplacePurchases $action,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', UserMarketItem::class);

        return MarketPurchaseResource::collection(
            $action->execute($request->user(), (int) ($request->validated('per_page') ?? 30)),
        );
    }

    public function equipped(Request $request, GetEquippedMarketItems $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', UserMarketItem::class);

        return UserMarketItemResource::collection($action->execute($request->user()));
    }

    public function purchase(PurchaseMarketItemRequest $request, PurchaseMarketItem $action): JsonResponse
    {
        $this->authorize('purchase', UserMarketItem::class);

        $inventory = $action->execute(
            $request->user(),
            new PurchaseMarketItemData(
                marketItemId: (string) $request->validated('market_item_id'),
                metadata: $request->validated('metadata'),
            ),
        );

        return response()->json([
            'data' => (new UserMarketItemResource($inventory))->resolve($request),
        ], 201);
    }

    public function equip(EquipMarketItemRequest $request, EquipMarketItem $action): JsonResponse
    {
        $this->authorize('equip', UserMarketItem::class);

        $inventory = $action->execute(
            $request->user(),
            new EquipMarketItemData(
                userMarketItemId: (string) $request->validated('user_market_item_id'),
            ),
        );

        return response()->json([
            'data' => (new UserMarketItemResource($inventory))->resolve($request),
        ]);
    }

    public function unequip(EquipMarketItemRequest $request, UnequipMarketItem $action): JsonResponse
    {
        $this->authorize('equip', UserMarketItem::class);

        $inventory = $action->execute(
            $request->user(),
            new EquipMarketItemData(
                userMarketItemId: (string) $request->validated('user_market_item_id'),
            ),
        );

        return response()->json([
            'data' => (new UserMarketItemResource($inventory))->resolve($request),
        ]);
    }
}
