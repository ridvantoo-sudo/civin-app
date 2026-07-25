<?php

namespace App\Features\Growth\Http\Controllers;

use App\Features\Growth\Actions\ManageGrowth;
use App\Features\Growth\DTOs\CreateCampaignData;
use App\Features\Growth\Http\Requests\CreateCampaignRequest;
use App\Features\Growth\Http\Resources\CampaignResource;
use App\Features\Growth\Models\GrowthCampaign;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CampaignController extends Controller
{
    public function index(Request $request, ManageGrowth $action): AnonymousResourceCollection
    {
        return CampaignResource::collection(
            $action->myCampaigns($request->user(), (int) $request->integer('per_page', 20)),
        );
    }

    public function store(CreateCampaignRequest $request, ManageGrowth $action): JsonResponse
    {
        $v = $request->validated();
        $campaign = $action->createCampaign(
            $request->user(),
            new CreateCampaignData(
                name: (string) $v['name'],
                channel: (string) $v['channel'],
                body: (string) $v['body'],
                subject: $v['subject'] ?? null,
                actionUrl: $v['action_url'] ?? null,
                audience: $v['audience'] ?? null,
                scheduledAt: isset($v['scheduled_at']) ? new \DateTimeImmutable($v['scheduled_at']) : null,
                metadata: $v['metadata'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new CampaignResource($campaign))->resolve($request),
        ], 201);
    }

    public function show(Request $request, GrowthCampaign $campaign): JsonResponse
    {
        abort_unless($campaign->created_by === $request->user()->getKey(), 403);

        return response()->json([
            'data' => (new CampaignResource($campaign))->resolve($request),
        ]);
    }

    public function dispatchNow(Request $request, GrowthCampaign $campaign, ManageGrowth $action): JsonResponse
    {
        $campaign = $action->dispatchCampaign($request->user(), $campaign);

        return response()->json([
            'data' => (new CampaignResource($campaign))->resolve($request),
        ]);
    }

    public function cancel(Request $request, GrowthCampaign $campaign, ManageGrowth $action): JsonResponse
    {
        $campaign = $action->cancelCampaign($request->user(), $campaign);

        return response()->json([
            'data' => (new CampaignResource($campaign))->resolve($request),
        ]);
    }
}
