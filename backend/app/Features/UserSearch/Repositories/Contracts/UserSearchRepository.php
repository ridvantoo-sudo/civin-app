<?php

namespace App\Features\UserSearch\Repositories\Contracts;

use App\Features\Users\Models\User;
use App\Features\UserSearch\DTOs\UserSearchCriteria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserSearchRepository
{
    public function search(User $viewer, UserSearchCriteria $criteria): LengthAwarePaginator;
}
