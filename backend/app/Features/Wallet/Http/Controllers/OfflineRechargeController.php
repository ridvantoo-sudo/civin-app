<?php

namespace App\Features\Wallet\Http\Controllers;

use App\Features\Wallet\Actions\ListOfflineRecharges;
use App\Features\Wallet\Actions\ReviewOfflineRecharge;
use App\Features\Wallet\Actions\SubmitOfflineRecharge;
use App\Features\Wallet\DTOs\ReviewOfflineRechargeData;
use App\Features\Wallet\DTOs\SubmitOfflineRechargeData;
use App\Features\Wallet\Http\Requests\ListOfflineRechargesRequest;
use App\Features\Wallet\Http\Requests\ReviewOfflineRechargeRequest as ReviewOfflineRechargeFormRequest;
use App\Features\Wallet\Http\Requests\SubmitOfflineRechargeRequest;
use App\Features\Wallet\Http\Resources\OfflineRechargeRequestResource;
use App\Features\Wallet\Models\OfflineRechargeRequest;
use App\Features\Wallet\Services\WalletService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

final class OfflineRechargeController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function index(ListOfflineRechargesRequest $request, ListOfflineRecharges $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OfflineRechargeRequest::class);

        return OfflineRechargeRequestResource::collection(
            $action->execute($request->user(), $request->user(), $request->perPage()),
        );
    }

    public function store(SubmitOfflineRechargeRequest $request, SubmitOfflineRecharge $action): JsonResponse
    {
        $this->authorize('create', OfflineRechargeRequest::class);

        $disk = (string) config('filesystems.secure_media');
        $path = $request->file('receipt')->store(
            'offline-recharges/'.$request->user()->getKey(),
            $disk,
        );

        try {
            $offlineRequest = $action->execute(
                $request->user(),
                new SubmitOfflineRechargeData(
                    coins: (int) $request->validated('coins'),
                    amount: (int) $request->validated('amount'),
                    currency: (string) $request->validated('currency'),
                    referenceNumber: (string) $request->validated('reference_number'),
                    receiptPath: $path,
                    metadata: $request->validated('metadata'),
                ),
            );
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        return response()->json([
            'data' => (new OfflineRechargeRequestResource($offlineRequest))->resolve($request),
        ], 201);
    }

    public function adminIndex(ListOfflineRechargesRequest $request): AnonymousResourceCollection
    {
        $this->authorize('reviewAny', OfflineRechargeRequest::class);

        return OfflineRechargeRequestResource::collection(
            $this->wallets->pendingOfflineRecharges($request->perPage()),
        );
    }

    public function review(
        ReviewOfflineRechargeFormRequest $request,
        OfflineRechargeRequest $offlineRechargeRequest,
        ReviewOfflineRecharge $action,
    ): OfflineRechargeRequestResource {
        $this->authorize('review', $offlineRechargeRequest);

        return new OfflineRechargeRequestResource($action->execute(
            $offlineRechargeRequest,
            $request->user(),
            new ReviewOfflineRechargeData(
                status: (string) $request->validated('status'),
                notes: $request->validated('notes'),
            ),
        ));
    }
}
