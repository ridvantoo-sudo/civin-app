<?php

namespace App\Features\Devices\Http\Controllers;

use App\Features\Devices\Http\Resources\DeviceResource;
use App\Features\Devices\Models\Device;
use App\Features\Devices\Services\DeviceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class DeviceController extends Controller
{
    public function __construct(private readonly DeviceService $devices) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return DeviceResource::collection($this->devices->forUser($request->user()));
    }

    public function destroy(Request $request, Device $device): Response
    {
        $this->authorize('delete', $device);

        $this->devices->remove($request->user(), $device);

        return response()->noContent();
    }
}
