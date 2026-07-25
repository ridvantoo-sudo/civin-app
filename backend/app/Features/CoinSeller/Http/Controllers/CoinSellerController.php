<?php

namespace App\Features\CoinSeller\Http\Controllers;

use App\Features\CoinSeller\Actions\ApplyAsCoinSeller;
use App\Features\CoinSeller\Actions\CreateRechargeCode;
use App\Features\CoinSeller\Actions\DirectSellCoins;
use App\Features\CoinSeller\Actions\GetCoinSellerDashboard;
use App\Features\CoinSeller\Actions\GetMyCoinSeller;
use App\Features\CoinSeller\Actions\ListCoinSellerCodes;
use App\Features\CoinSeller\Actions\ListCoinSellerCommissions;
use App\Features\CoinSeller\Actions\ListCoinSellerInventory;
use App\Features\CoinSeller\Actions\ListCoinSellerSales;
use App\Features\CoinSeller\Actions\RedeemRechargeCode;
use App\Features\CoinSeller\Actions\RevokeRechargeCode;
use App\Features\CoinSeller\DTOs\ApplyCoinSellerData;
use App\Features\CoinSeller\DTOs\CreateRechargeCodeData;
use App\Features\CoinSeller\DTOs\DirectSellData;
use App\Features\CoinSeller\DTOs\RedeemRechargeCodeData;
use App\Features\CoinSeller\Http\Requests\ApplyCoinSellerRequest;
use App\Features\CoinSeller\Http\Requests\CreateRechargeCodeRequest;
use App\Features\CoinSeller\Http\Requests\DirectSellRequest;
use App\Features\CoinSeller\Http\Requests\ListCoinSellerPagedRequest;
use App\Features\CoinSeller\Http\Requests\RedeemRechargeCodeRequest;
use App\Features\CoinSeller\Http\Resources\CoinRechargeCodeResource;
use App\Features\CoinSeller\Http\Resources\CoinSellerCommissionResource;
use App\Features\CoinSeller\Http\Resources\CoinSellerInventoryLogResource;
use App\Features\CoinSeller\Http\Resources\CoinSellerResource;
use App\Features\CoinSeller\Http\Resources\CoinSellerSaleResource;
use App\Features\CoinSeller\Models\CoinRechargeCode;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CoinSellerController extends Controller
{
    public function apply(ApplyCoinSellerRequest $request, ApplyAsCoinSeller $action): JsonResponse
    {
        $this->authorize('apply', CoinSeller::class);

        $seller = $action->execute(
            $request->user(),
            new ApplyCoinSellerData(
                displayName: (string) $request->validated('display_name'),
                contactInfo: $request->validated('contact_info'),
                applicationMessage: $request->validated('application_message'),
            ),
        );

        return response()->json([
            'data' => (new CoinSellerResource($seller))->resolve($request),
        ], 201);
    }

    public function me(Request $request, GetMyCoinSeller $action): JsonResponse
    {
        $this->authorize('viewOwn', CoinSeller::class);

        $seller = $action->execute($request->user());

        return response()->json([
            'data' => $seller === null ? null : (new CoinSellerResource($seller))->resolve($request),
        ]);
    }

    public function dashboard(Request $request, GetCoinSellerDashboard $action): JsonResponse
    {
        $this->authorize('viewOwn', CoinSeller::class);

        $payload = $action->execute($request->user());

        return response()->json([
            'data' => [
                'seller' => (new CoinSellerResource($payload['seller']))->resolve($request),
                'stats' => $payload['stats'],
            ],
        ]);
    }

    public function sell(DirectSellRequest $request, DirectSellCoins $action): JsonResponse
    {
        $this->authorize('viewOwn', CoinSeller::class);

        $sale = $action->execute(
            $request->user(),
            new DirectSellData(
                buyerId: (string) $request->validated('buyer_id'),
                coins: (int) $request->validated('coins'),
                priceAmount: (int) ($request->validated('price_amount') ?? 0),
                currency: (string) ($request->validated('currency') ?? 'USD'),
            ),
        );

        return response()->json([
            'data' => (new CoinSellerSaleResource($sale))->resolve($request),
        ], 201);
    }

    public function createCode(CreateRechargeCodeRequest $request, CreateRechargeCode $action): JsonResponse
    {
        $this->authorize('viewOwn', CoinSeller::class);

        $code = $action->execute(
            $request->user(),
            new CreateRechargeCodeData(
                coins: (int) $request->validated('coins'),
                expiresInHours: $request->validated('expires_in_hours') === null
                    ? 72
                    : (int) $request->validated('expires_in_hours'),
            ),
        );

        return response()->json([
            'data' => (new CoinRechargeCodeResource($code))->resolve($request),
        ], 201);
    }

    public function revokeCode(
        Request $request,
        CoinRechargeCode $coinRechargeCode,
        RevokeRechargeCode $action,
    ): JsonResponse {
        $this->authorize('viewOwn', CoinSeller::class);

        $code = $action->execute($request->user(), $coinRechargeCode);

        return response()->json([
            'data' => (new CoinRechargeCodeResource($code))->resolve($request),
        ]);
    }

    public function redeem(RedeemRechargeCodeRequest $request, RedeemRechargeCode $action): JsonResponse
    {
        $this->authorize('redeem', CoinSeller::class);

        $sale = $action->execute(
            $request->user(),
            new RedeemRechargeCodeData(code: (string) $request->validated('code')),
        );

        return response()->json([
            'data' => (new CoinSellerSaleResource($sale))->resolve($request),
        ]);
    }

    public function sales(ListCoinSellerPagedRequest $request, ListCoinSellerSales $action): AnonymousResourceCollection
    {
        $this->authorize('viewOwn', CoinSeller::class);

        return CoinSellerSaleResource::collection($action->execute($request->user(), $request->perPage()));
    }

    public function codes(ListCoinSellerPagedRequest $request, ListCoinSellerCodes $action): AnonymousResourceCollection
    {
        $this->authorize('viewOwn', CoinSeller::class);

        return CoinRechargeCodeResource::collection($action->execute($request->user(), $request->perPage()));
    }

    public function inventory(ListCoinSellerPagedRequest $request, ListCoinSellerInventory $action): AnonymousResourceCollection
    {
        $this->authorize('viewOwn', CoinSeller::class);

        return CoinSellerInventoryLogResource::collection($action->execute($request->user(), $request->perPage()));
    }

    public function commissions(
        ListCoinSellerPagedRequest $request,
        ListCoinSellerCommissions $action,
    ): AnonymousResourceCollection {
        $this->authorize('viewOwn', CoinSeller::class);

        return CoinSellerCommissionResource::collection($action->execute($request->user(), $request->perPage()));
    }
}
