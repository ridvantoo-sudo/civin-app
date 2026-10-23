<?php

namespace App\Features\Wallet\Actions;

use App\Features\Users\Models\User;
use App\Features\Wallet\DTOs\RequestWithdrawData;
use App\Features\Wallet\Models\WithdrawRequest;
use App\Features\Wallet\Services\WalletService;

final readonly class RequestWithdraw
{
    public function __construct(private WalletService $wallets) {}

    public function execute(User $user, RequestWithdrawData $data): WithdrawRequest
    {
        return $this->wallets->withdraw($user, $data);
    }
}
