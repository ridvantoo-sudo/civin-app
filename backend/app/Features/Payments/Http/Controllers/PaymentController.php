<?php

namespace App\Features\Payments\Http\Controllers;

use App\Features\Payments\Actions\ManagePayments;
use App\Features\Payments\DTOs\CreatePaymentIntentData;
use App\Features\Payments\Http\Requests\CreatePaymentIntentRequest;
use App\Features\Payments\Http\Requests\RefundPaymentRequest;
use App\Features\Payments\Http\Resources\PaymentTransactionResource;
use App\Features\Payments\Models\PaymentTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PaymentController extends Controller
{
    public function gateways(ManagePayments $action): JsonResponse
    {
        return response()->json(['data' => $action->availableGateways()]);
    }

    public function createIntent(CreatePaymentIntentRequest $request, ManagePayments $action): JsonResponse
    {
        $this->authorize('create', PaymentTransaction::class);

        $validated = $request->validated();
        $transaction = $action->createIntent(
            $request->user(),
            new CreatePaymentIntentData(
                gateway: (string) $validated['gateway'],
                payableType: (string) $validated['payable_type'],
                payableId: (string) $validated['payable_id'],
                currency: $validated['currency'] ?? null,
                returnUrl: $validated['return_url'] ?? null,
                token: $validated['token'] ?? null,
                metadata: $validated['metadata'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new PaymentTransactionResource($transaction))->resolve($request),
        ], 201);
    }

    public function index(Request $request, ManagePayments $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PaymentTransaction::class);

        return PaymentTransactionResource::collection(
            $action->list($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function show(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);

        return response()->json([
            'data' => (new PaymentTransactionResource($transaction))->resolve($request),
        ]);
    }

    public function refund(RefundPaymentRequest $request, PaymentTransaction $transaction, ManagePayments $action): JsonResponse
    {
        $this->authorize('refund', $transaction);

        $validated = $request->validated();
        $action->refund(
            $transaction,
            isset($validated['amount']) ? (int) $validated['amount'] : null,
            $validated['reason'] ?? null,
            $request->user(),
        );

        return response()->json([
            'data' => (new PaymentTransactionResource($transaction->fresh()))->resolve($request),
        ]);
    }
}
