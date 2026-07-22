<?php

namespace App\Features\Users\Http\Controllers;

use App\Features\Users\Http\Requests\UpdateUserRequest;
use App\Features\Users\Http\Resources\UserResource;
use App\Features\Users\Services\UserService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class CurrentUserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(UpdateUserRequest $request): UserResource
    {
        return new UserResource($this->users->update($request->user(), $request->validated()));
    }
}
