<?php

namespace App\Features\UserStatus\Http\Controllers;

use App\Features\UserStatus\Actions\UpdateUserStatus;
use App\Features\UserStatus\DTOs\UpdateUserStatusData;
use App\Features\UserStatus\Http\Requests\UpdateUserStatusRequest;
use App\Features\UserStatus\Http\Resources\UserStatusResource;
use App\Features\UserStatus\Services\UserStatusService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class UserStatusController extends Controller
{
    public function __construct(private readonly UserStatusService $statuses) {}

    public function show(Request $request): UserStatusResource
    {
        return new UserStatusResource($this->statuses->show($request->user()));
    }

    public function update(UpdateUserStatusRequest $request, UpdateUserStatus $action): UserStatusResource
    {
        return new UserStatusResource($action->execute(
            $request->user(),
            new UpdateUserStatusData(
                $request->has('is_online') ? $request->boolean('is_online') : null,
                $request->has('is_live') ? $request->boolean('is_live') : null,
            ),
        ));
    }
}
