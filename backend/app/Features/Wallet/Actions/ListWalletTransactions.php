<?php

namespace App\Features\Wallet\Actions;

use App\Features\Users\Models\User;
use App\Features\Wallet\Services\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListWalletTransactions
{
    public function __construct(private WalletService $wallets) {}

    public function execute(User $actor, User $subject, int $perPage): LengthAwarePaginator
    {
        return $this->wallets->transactions($actor, $subject, $perPage);
    }
}
