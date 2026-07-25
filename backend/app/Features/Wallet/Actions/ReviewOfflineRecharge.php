<?php

namespace App\Features\Wallet\Actions;

use App\Features\Users\Models\User;
use App\Features\Wallet\DTOs\ReviewOfflineRechargeData;
use App\Features\Wallet\Models\OfflineRechargeRequest;
use App\Features\Wallet\Services\WalletService;

final readonly class ReviewOfflineRecharge
{
    public function __construct(private WalletService $wallets) {}

    public function execute(
        OfflineRechargeRequest $request,
        User $reviewer,
        ReviewOfflineRechargeData $data,
    ): OfflineRechargeRequest {
        return $this->wallets->reviewOfflineRecharge($request, $reviewer, $data);
    }
}
