<?php

namespace App\Features\Wallet\Actions;

use App\Features\Users\Models\User;
use App\Features\Wallet\DTOs\RechargeWalletData;
use App\Features\Wallet\Models\RechargeOrder;
use App\Features\Wallet\Services\WalletService;

final readonly class RechargeWallet
{
    public function __construct(private WalletService $wallets) {}

    public function execute(User $user, RechargeWalletData $data): RechargeOrder
    {
        return $this->wallets->recharge($user, $data);
    }
}
