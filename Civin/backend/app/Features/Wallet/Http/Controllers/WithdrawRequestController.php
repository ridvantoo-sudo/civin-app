<?php

namespace App\Features\Wallet\Http\Controllers;

use App\Features\Wallet\Actions\ReviewWithdrawRequest;
use App\Features\Wallet\DTOs\ReviewWithdrawData;
use App\Features\Wallet\Http\Requests\ListWithdrawRequestsRequest;
use App\Features\Wallet\Http\Requests\ReviewWithdrawRequest as ReviewWithdrawFormRequest;
use App\Features\Wallet\Http\Resources\WithdrawRequestResource;
use App\Features\Wallet\Models\WithdrawRequest;
use App\Features\Wallet\Services\WalletService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WithdrawRequestController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function index(ListWithdrawRequestsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('reviewAny', WithdrawRequest::class);

        return WithdrawRequestResource::collection(
            $this->wallets->pendingWithdrawals($request->perPage()),
        );
    }

    public function review(
        ReviewWithdrawFormRequest $request,
        WithdrawRequest $withdrawRequest,
        ReviewWithdrawRequest $action,
    ): WithdrawRequestResource {
        $this->authorize('review', $withdrawRequest);

        return new WithdrawRequestResource($action->execute(
            $withdrawRequest,
            $request->user(),
            new ReviewWithdrawData(
                status: (string) $request->validated('status'),
                notes: $request->validated('notes'),
            ),
        ));
    }
}
