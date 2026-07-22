<?php

namespace App\Features\Blocking\Http\Controllers;

use App\Features\Blocking\Actions\BlockUser;
use App\Features\Blocking\DTOs\BlockUserData;
use App\Features\Blocking\Http\Resources\BlockResource;
use App\Features\Blocking\Services\BlockingService;
use App\Features\Followers\Http\Requests\FollowUserRequest;
use App\Features\Followers\Http\Requests\ListFollowersRequest;
use App\Features\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class BlockController extends Controller
{
    public function __construct(private readonly BlockingService $blocking) {}

    public function index(ListFollowersRequest $request): AnonymousResourceCollection
    {
        return BlockResource::collection($this->blocking->index($request->user(), $request->perPage()));
    }

    public function store(FollowUserRequest $request, User $user, BlockUser $action): BlockResource
    {
        return new BlockResource($action->execute(
            $request->user(),
            new BlockUserData((string) $request->validated('user_id')),
        ));
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->blocking->unblock($request->user(), $user);

        return response()->noContent();
    }
}
