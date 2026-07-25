<?php

namespace App\Features\Growth\Http\Controllers;

use App\Features\Growth\Actions\ManageGrowth;
use App\Features\Growth\DTOs\CreateInviteData;
use App\Features\Growth\Http\Requests\CreateInviteRequest;
use App\Features\Growth\Http\Resources\InviteResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InviteController extends Controller
{
    public function index(Request $request, ManageGrowth $action): AnonymousResourceCollection
    {
        return InviteResource::collection(
            $action->myInvites($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function store(CreateInviteRequest $request, ManageGrowth $action): JsonResponse
    {
        $validated = $request->validated();
        $invite = $action->createInvite(
            $request->user(),
            new CreateInviteData(
                channel: (string) ($validated['channel'] ?? 'link'),
                target: $validated['target'] ?? null,
                metadata: $validated['metadata'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new InviteResource($invite))->resolve($request),
        ], 201);
    }

    public function accept(Request $request, string $token, ManageGrowth $action): JsonResponse
    {
        $invite = $action->acceptInvite($request->user(), $token);

        return response()->json([
            'data' => (new InviteResource($invite))->resolve($request),
        ]);
    }
}
