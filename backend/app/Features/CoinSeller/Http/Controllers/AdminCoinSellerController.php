<?php

namespace App\Features\CoinSeller\Http\Controllers;

use App\Features\CoinSeller\Actions\AllocateCoinSellerInventory;
use App\Features\CoinSeller\Actions\ListCoinSellersForAdmin;
use App\Features\CoinSeller\Actions\ReviewCoinSeller;
use App\Features\CoinSeller\DTOs\AllocateInventoryData;
use App\Features\CoinSeller\DTOs\ReviewCoinSellerData;
use App\Features\CoinSeller\Http\Requests\AllocateInventoryRequest;
use App\Features\CoinSeller\Http\Requests\ListCoinSellerPagedRequest;
use App\Features\CoinSeller\Http\Requests\ReviewCoinSellerRequest;
use App\Features\CoinSeller\Http\Resources\CoinSellerResource;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminCoinSellerController extends Controller
{
    public function index(ListCoinSellerPagedRequest $request, ListCoinSellersForAdmin $action): AnonymousResourceCollection
    {
        $this->authorize('viewAnyAdmin', CoinSeller::class);

        return CoinSellerResource::collection(
            $action->execute($request->user(), $request->status(), $request->perPage()),
        );
    }

    public function review(
        ReviewCoinSellerRequest $request,
        CoinSeller $coinSeller,
        ReviewCoinSeller $action,
    ): JsonResponse {
        $this->authorize('review', CoinSeller::class);

        $seller = $action->execute(
            $coinSeller,
            $request->user(),
            new ReviewCoinSellerData(
                status: (string) $request->validated('status'),
                commissionRate: $request->validated('commission_rate') === null
                    ? null
                    : (float) $request->validated('commission_rate'),
                notes: $request->validated('notes'),
            ),
        );

        return response()->json([
            'data' => (new CoinSellerResource($seller))->resolve($request),
        ]);
    }

    public function allocateInventory(
        AllocateInventoryRequest $request,
        CoinSeller $coinSeller,
        AllocateCoinSellerInventory $action,
    ): JsonResponse {
        $this->authorize('allocate', CoinSeller::class);

        $seller = $action->execute(
            $coinSeller,
            $request->user(),
            new AllocateInventoryData(
                coins: (int) $request->validated('coins'),
                notes: $request->validated('notes'),
            ),
        );

        return response()->json([
            'data' => (new CoinSellerResource($seller))->resolve($request),
        ]);
    }
}
