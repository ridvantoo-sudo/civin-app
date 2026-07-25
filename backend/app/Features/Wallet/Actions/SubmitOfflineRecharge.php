<?php

namespace App\Features\Wallet\Actions;

use App\Features\Users\Models\User;
use App\Features\Wallet\DTOs\SubmitOfflineRechargeData;
use App\Features\Wallet\Models\OfflineRechargeRequest;
use App\Features\Wallet\Services\WalletService;

final readonly class SubmitOfflineRecharge
{
    public function __construct(private WalletService $wallets) {}

    public function execute(User $user, SubmitOfflineRechargeData $data): OfflineRechargeRequest
    {
        return $this->wallets->submitOfflineRecharge($user, $data);
    }
}
