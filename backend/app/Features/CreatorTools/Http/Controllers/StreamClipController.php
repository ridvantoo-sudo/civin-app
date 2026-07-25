<?php

namespace App\Features\CreatorTools\Http\Controllers;

use App\Features\CreatorTools\Actions\ManageCreatorTools;
use App\Features\CreatorTools\DTOs\CreateClipData;
use App\Features\CreatorTools\Http\Requests\CreateClipRequest;
use App\Features\CreatorTools\Http\Resources\StreamClipResource;
use App\Features\CreatorTools\Support\ClipKind;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StreamClipController extends Controller
{
    public function index(Request $request, ManageCreatorTools $action): AnonymousResourceCollection
    {
        return StreamClipResource::collection(
            $action->myClips($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function roomIndex(Request $request, LiveRoom $room, ManageCreatorTools $action): AnonymousResourceCollection
    {
        $kind = $request->query('kind');
        if ($kind !== null && ! in_array($kind, ClipKind::all(), true)) {
            $kind = null;
        }

        return StreamClipResource::collection(
            $action->roomClips($room, is_string($kind) ? $kind : null, (int) $request->integer('per_page', 20)),
        );
    }

    public function store(CreateClipRequest $request, LiveRoom $room, ManageCreatorTools $action): JsonResponse
    {
        $v = $request->validated();
        $clip = $action->createClip(
            $request->user(),
            $room,
            new CreateClipData(
                title: (string) $v['title'],
                startSeconds: (int) $v['start_seconds'],
                endSeconds: (int) $v['end_seconds'],
                kind: (string) ($v['kind'] ?? ClipKind::CLIP),
                thumbnailUrl: $v['thumbnail_url'] ?? null,
                metadata: $v['metadata'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new StreamClipResource($clip))->resolve($request),
        ], 201);
    }
}
