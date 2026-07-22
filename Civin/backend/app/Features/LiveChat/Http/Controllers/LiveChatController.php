<?php

namespace App\Features\LiveChat\Http\Controllers;

use App\Features\LiveChat\Actions\DeleteLiveMessage;
use App\Features\LiveChat\Actions\SendLiveMessage;
use App\Features\LiveChat\DTOs\SendLiveMessageData;
use App\Features\LiveChat\Http\Requests\ListLiveMessagesRequest;
use App\Features\LiveChat\Http\Requests\SendLiveMessageRequest;
use App\Features\LiveChat\Http\Resources\LiveMessageResource;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveChat\Services\LiveChatService;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class LiveChatController extends Controller
{
    public function __construct(private readonly LiveChatService $chat) {}

    public function index(ListLiveMessagesRequest $request, LiveRoom $room): AnonymousResourceCollection
    {
        $this->authorize('viewMessages', $room);

        return LiveMessageResource::collection(
            $this->chat->list($room, $request->user(), $request->perPage()),
        );
    }

    public function store(
        SendLiveMessageRequest $request,
        LiveRoom $room,
        SendLiveMessage $action,
    ): JsonResponse {
        $this->authorize('sendMessage', $room);

        $message = $action->execute(
            $room,
            $request->user(),
            new SendLiveMessageData(
                (string) $request->validated('message'),
                metadata: $request->validated('metadata'),
            ),
        );

        return response()->json([
            'data' => (new LiveMessageResource($message))->resolve($request),
        ], 201);
    }

    public function destroy(
        LiveRoom $room,
        LiveMessage $message,
        DeleteLiveMessage $action,
    ): Response {
        abort_unless($message->room_id === $room->getKey(), 404);

        $this->authorize('delete', $message);
        $action->execute($message, request()->user());

        return response()->noContent();
    }
}
