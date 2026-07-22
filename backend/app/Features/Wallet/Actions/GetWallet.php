<?php

namespace App\Features\Wallet\Actions;

use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Services\WalletService;

final readonly class GetWallet
{
    public function __construct(private WalletService $wallets) {}

    public function execute(User $actor, User $subject): Wallet
    {
        return $this->wallets->getForUser($actor, $subject);
    }
}
