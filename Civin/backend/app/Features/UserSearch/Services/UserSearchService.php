<?php

namespace App\Features\UserSearch\Services;

use App\Features\Users\Models\User;
use App\Features\UserSearch\DTOs\UserSearchCriteria;
use App\Features\UserSearch\Repositories\Contracts\UserSearchRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class UserSearchService
{
    public function __construct(private UserSearchRepository $users) {}

    public function search(User $viewer, UserSearchCriteria $criteria): LengthAwarePaginator
    {
        return $this->users->search($viewer, $criteria);
    }
}
