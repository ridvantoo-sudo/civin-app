<?php

namespace App\Features\Kyc\Actions;

use App\Features\Kyc\Services\KycService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListKycForAdmin
{
    public function __construct(private KycService $kyc) {}

    public function execute(User $admin, ?string $status, int $perPage): LengthAwarePaginator
    {
        return $this->kyc->listForAdmin($admin, $status, $perPage);
    }
}
