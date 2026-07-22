<?php

namespace App\Features\Followers\Http\Controllers;

use App\Features\Followers\Actions\FollowUser;
use App\Features\Followers\DTOs\FollowUserData;
use App\Features\Followers\Http\Requests\FollowUserRequest;
use App\Features\Followers\Http\Requests\ListFollowersRequest;
use App\Features\Followers\Http\Resources\FollowResource;
use App\Features\Followers\Models\Follow;
use App\Features\Followers\Services\FollowerService;
use App\Features\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class FollowerController extends Controller
{
    public function __construct(private readonly FollowerService $followers) {}

    public function store(FollowUserRequest $request, User $user, FollowUser $action): FollowResource
    {
        $follow = $action->execute(
            $request->user(),
            new FollowUserData((string) $request->validated('user_id')),
        );

        return new FollowResource($follow);
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->followers->unfollow($request->user(), $user);

        return response()->noContent();
    }

    public function followers(ListFollowersRequest $request, User $user): AnonymousResourceCollection
    {
        return FollowResource::collection($this->followers->followers(
            $request->user(),
            $user,
            $request->perPage(),
        ));
    }

    public function following(ListFollowersRequest $request, User $user): AnonymousResourceCollection
    {
        return FollowResource::collection($this->followers->following(
            $request->user(),
            $user,
            $request->perPage(),
        ));
    }

    public function requests(ListFollowersRequest $request): AnonymousResourceCollection
    {
        return FollowResource::collection($this->followers->requests($request->user(), $request->perPage()));
    }

    public function accept(Request $request, Follow $follow): FollowResource
    {
        $this->authorize('respond', $follow);

        return new FollowResource($this->followers->accept($request->user(), $follow));
    }

    public function reject(Request $request, Follow $follow): Response
    {
        $this->authorize('respond', $follow);
        $this->followers->reject($request->user(), $follow);

        return response()->noContent();
    }
}
