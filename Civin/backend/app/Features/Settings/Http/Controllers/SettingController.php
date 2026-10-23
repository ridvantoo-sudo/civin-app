<?php

namespace App\Features\Settings\Http\Controllers;

use App\Features\Settings\Http\Requests\UpdateUserSettingsRequest;
use App\Features\Settings\Services\SettingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function publicIndex(): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->publicValues(),
        ]);
    }

    public function userIndex(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->settings->userValues($request->user())]);
    }

    public function userUpdate(UpdateUserSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->updateUserValues($request->user(), $request->validated('settings')),
        ]);
    }
}
