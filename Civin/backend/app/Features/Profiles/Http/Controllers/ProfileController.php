<?php

namespace App\Features\Profiles\Http\Controllers;

use App\Features\Profiles\Http\Requests\UpdateProfileRequest;
use App\Features\Profiles\Http\Resources\ProfileResource;
use App\Features\Profiles\Services\ProfileService;
use App\Features\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function show(Request $request): ProfileResource
    {
        return new ProfileResource($this->profiles->show($request->user()));
    }

    public function update(UpdateProfileRequest $request): ProfileResource
    {
        return new ProfileResource($this->profiles->update($request->user(), $request->validated()));
    }

    public function publicShow(Request $request, User $user): ProfileResource
    {
        return new ProfileResource($this->profiles->publicProfile($request->user(), $user));
    }
}
