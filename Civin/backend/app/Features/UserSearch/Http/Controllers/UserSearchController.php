<?php

namespace App\Features\UserSearch\Http\Controllers;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use App\Features\UserSearch\Actions\SearchUsers;
use App\Features\UserSearch\DTOs\UserSearchCriteria;
use App\Features\UserSearch\Http\Requests\SearchUsersRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class UserSearchController extends Controller
{
    public function __invoke(
        SearchUsersRequest $request,
        SearchUsers $action,
    ): AnonymousResourceCollection {
        return SocialUserResource::collection($action->execute(
            $request->user(),
            new UserSearchCriteria(
                $request->validated('query'),
                $request->validated('country'),
                $request->has('is_online') ? $request->boolean('is_online') : null,
                (int) $request->validated('per_page', 20),
            ),
        ));
    }
}
