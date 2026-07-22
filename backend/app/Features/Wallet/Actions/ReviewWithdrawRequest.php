<?php

namespace App\Features\Wallet\Actions;

use App\Features\Users\Models\User;
use App\Features\Wallet\DTOs\ReviewWithdrawData;
use App\Features\Wallet\Models\WithdrawRequest;
use App\Features\Wallet\Services\WalletService;

final readonly class ReviewWithdrawRequest
{
    public function __construct(private WalletService $wallets) {}

    public function execute(WithdrawRequest $request, User $reviewer, ReviewWithdrawData $data): WithdrawRequest
    {
        return $this->wallets->reviewWithdraw($request, $reviewer, $data);
    }
}
