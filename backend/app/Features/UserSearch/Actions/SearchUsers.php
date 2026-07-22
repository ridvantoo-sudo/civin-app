<?php

namespace App\Features\UserSearch\Actions;

use App\Features\Users\Models\User;
use App\Features\UserSearch\DTOs\UserSearchCriteria;
use App\Features\UserSearch\Services\UserSearchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class SearchUsers
{
    public function __construct(private UserSearchService $search) {}

    public function execute(User $viewer, UserSearchCriteria $criteria): LengthAwarePaginator
    {
        return $this->search->search($viewer, $criteria);
    }
}
