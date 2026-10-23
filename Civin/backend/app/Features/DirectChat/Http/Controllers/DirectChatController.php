<?php

namespace App\Features\DirectChat\Http\Controllers;

use App\Features\DirectChat\Http\Requests\SendDirectMessageRequest;
use App\Features\DirectChat\Http\Resources\ConversationResource;
use App\Features\DirectChat\Http\Resources\DirectMessageResource;
use App\Features\DirectChat\Models\Conversation;
use App\Features\DirectChat\Services\DirectChatService;
use App\Features\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DirectChatController extends Controller
{
    public function __construct(private readonly DirectChatService $chat) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ConversationResource::collection($this->chat->list($request->user(), $this->perPage($request)));
    }

    public function open(Request $request, User $user): JsonResponse
    {
        $conversation = $this->chat->open($request->user(), $user);

        return response()->json([
            'data' => (new ConversationResource($conversation))->resolve($request),
        ]);
    }

    public function messages(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        return DirectMessageResource::collection(
            $this->chat->messages($request->user(), $conversation, $this->perPage($request)),
        );
    }

    public function send(SendDirectMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $message = $this->chat->send(
            $request->user(),
            $conversation,
            (string) $request->validated('body'),
        );

        return response()->json([
            'data' => (new DirectMessageResource($message))->resolve($request),
        ], 201);
    }

    private function perPage(Request $request): int
    {
        return min(50, max(1, (int) $request->query('per_page', 30)));
    }
}
