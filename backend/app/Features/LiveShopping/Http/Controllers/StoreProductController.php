<?php

namespace App\Features\LiveShopping\Http\Controllers;

use App\Features\LiveShopping\Actions\ManageStoreProducts;
use App\Features\LiveShopping\DTOs\UpsertStoreProductData;
use App\Features\LiveShopping\Http\Requests\UpsertStoreProductRequest;
use App\Features\LiveShopping\Http\Resources\StoreProductResource;
use App\Features\LiveShopping\Models\StoreProduct;
use App\Features\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StoreProductController extends Controller
{
    public function index(Request $request, ManageStoreProducts $action): AnonymousResourceCollection
    {
        $this->authorize('create', StoreProduct::class);

        return StoreProductResource::collection($action->list($request->user()));
    }

    public function offlineStore(User $user, ManageStoreProducts $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StoreProduct::class);

        return StoreProductResource::collection($action->offline($user));
    }

    public function store(UpsertStoreProductRequest $request, ManageStoreProducts $action): JsonResponse
    {
        $this->authorize('create', StoreProduct::class);

        $product = $action->create($request->user(), $this->dto($request));

        return response()->json([
            'data' => (new StoreProductResource($product))->resolve($request),
        ], 201);
    }

    public function update(
        UpsertStoreProductRequest $request,
        StoreProduct $product,
        ManageStoreProducts $action,
    ): JsonResponse {
        $this->authorize('update', $product);

        $product = $action->update($request->user(), $product, $this->dto($request));

        return response()->json([
            'data' => (new StoreProductResource($product))->resolve($request),
        ]);
    }

    public function destroy(Request $request, StoreProduct $product, ManageStoreProducts $action): JsonResponse
    {
        $this->authorize('delete', $product);

        $product = $action->remove($request->user(), $product);

        return response()->json([
            'data' => (new StoreProductResource($product))->resolve($request),
        ]);
    }

    private function dto(UpsertStoreProductRequest $request): UpsertStoreProductData
    {
        $validated = $request->validated();

        return new UpsertStoreProductData(
            name: (string) $validated['name'],
            priceCoins: (int) $validated['price_coins'],
            stock: (int) $validated['stock'],
            description: $validated['description'] ?? null,
            imageUrl: $validated['image_url'] ?? null,
            isOfflineAvailable: (bool) ($validated['is_offline_available'] ?? true),
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            metadata: $validated['metadata'] ?? null,
        );
    }
}
