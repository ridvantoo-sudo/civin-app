<?php

namespace App\Features\Notifications\Http\Controllers;

use App\Features\Notifications\Http\Resources\NotificationResource;
use App\Features\Notifications\Services\NotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return NotificationResource::collection($this->notifications->paginate($request->user()));
    }

    public function read(Request $request, string $notification): NotificationResource
    {
        return new NotificationResource($this->notifications->read($request->user(), $notification));
    }

    public function readAll(Request $request): Response
    {
        $this->notifications->readAll($request->user());

        return response()->noContent();
    }

    public function destroy(Request $request, string $notification): Response
    {
        $this->notifications->delete($request->user(), $notification);

        return response()->noContent();
    }
}
