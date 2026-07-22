<?php

namespace App\Features\Wallet\Http\Controllers;

use App\Features\Wallet\Actions\GetWallet;
use App\Features\Wallet\Actions\ListWalletTransactions;
use App\Features\Wallet\Actions\RechargeWallet;
use App\Features\Wallet\Actions\RequestWithdraw;
use App\Features\Wallet\DTOs\RechargeWalletData;
use App\Features\Wallet\DTOs\RequestWithdrawData;
use App\Features\Wallet\Http\Requests\ListWalletTransactionsRequest;
use App\Features\Wallet\Http\Requests\RechargeWalletRequest;
use App\Features\Wallet\Http\Requests\RequestWithdrawRequest;
use App\Features\Wallet\Http\Resources\RechargeOrderResource;
use App\Features\Wallet\Http\Resources\WalletResource;
use App\Features\Wallet\Http\Resources\WalletTransactionResource;
use App\Features\Wallet\Http\Resources\WithdrawRequestResource;
use App\Features\Wallet\Models\WithdrawRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WalletController extends Controller
{
    public function show(GetWallet $action): WalletResource
    {
        $user = request()->user();
        $wallet = $action->execute($user, $user);
        $this->authorize('view', $wallet);

        return new WalletResource($wallet);
    }

    public function transactions(ListWalletTransactionsRequest $request, ListWalletTransactions $action): AnonymousResourceCollection
    {
        return WalletTransactionResource::collection(
            $action->execute($request->user(), $request->user(), $request->perPage()),
        );
    }

    public function recharge(RechargeWalletRequest $request, RechargeWallet $action): JsonResponse
    {
        $order = $action->execute(
            $request->user(),
            new RechargeWalletData(
                packageName: (string) $request->validated('package_name'),
                coins: (int) $request->validated('coins'),
                price: (int) $request->validated('price'),
                currency: (string) $request->validated('currency'),
                paymentProvider: (string) $request->validated('payment_provider'),
                transactionId: (string) $request->validated('transaction_id'),
                metadata: $request->validated('metadata'),
            ),
        );

        return response()->json([
            'data' => (new RechargeOrderResource($order))->resolve($request),
        ], $order->wasRecentlyCreated ? 201 : 200);
    }

    public function withdraw(RequestWithdrawRequest $request, RequestWithdraw $action): JsonResponse
    {
        $this->authorize('create', WithdrawRequest::class);

        $withdrawRequest = $action->execute(
            $request->user(),
            new RequestWithdrawData(
                diamonds: (int) $request->validated('diamonds'),
                amount: (int) $request->validated('amount'),
                metadata: $request->validated('metadata'),
            ),
        );

        return response()->json([
            'data' => (new WithdrawRequestResource($withdrawRequest))->resolve($request),
        ], 201);
    }
}
