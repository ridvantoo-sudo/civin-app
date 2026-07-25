<?php

namespace App\Features\Ads\Http\Controllers;

use App\Features\Ads\Actions\CreateAd;
use App\Features\Ads\Actions\DeleteAd;
use App\Features\Ads\Actions\GetAdAnalytics;
use App\Features\Ads\Actions\GetAdEligibility;
use App\Features\Ads\Actions\ListMyAds;
use App\Features\Ads\Actions\PauseAd;
use App\Features\Ads\Actions\ResumeAd;
use App\Features\Ads\Actions\ServeAds;
use App\Features\Ads\Actions\ShowAd;
use App\Features\Ads\Actions\TrackAdEvent;
use App\Features\Ads\Actions\UpdateAd;
use App\Features\Ads\DTOs\CreateAdCampaignData;
use App\Features\Ads\DTOs\RecordAdEventData;
use App\Features\Ads\DTOs\UpdateAdCampaignData;
use App\Features\Ads\Http\Requests\CreateAdRequest;
use App\Features\Ads\Http\Requests\ServeAdsRequest;
use App\Features\Ads\Http\Requests\TrackAdEventRequest;
use App\Features\Ads\Http\Requests\UpdateAdRequest;
use App\Features\Ads\Http\Resources\AdCampaignResource;
use App\Features\Ads\Models\AdCampaign;
use App\Features\Ads\Repositories\Contracts\AdRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AdController extends Controller
{
    public function eligibility(Request $request, GetAdEligibility $action): JsonResponse
    {
        $this->authorize('viewAny', AdCampaign::class);
        $request->user()?->loadMissing('profile');

        return response()->json([
            'data' => $action->execute($request->user()),
        ]);
    }

    public function serve(string $placement, ServeAdsRequest $request, ServeAds $action): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AdCampaign::class);

        $validated = $request->validated();
        $viewer = $request->user();
        $viewer?->loadMissing(['profile.country']);

        return AdCampaignResource::collection(
            $action->execute(
                $placement,
                $viewer,
                $validated['context_id'] ?? null,
                isset($validated['limit']) ? (int) $validated['limit'] : null,
            ),
        );
    }

    public function mine(Request $request, ListMyAds $action): AnonymousResourceCollection
    {
        $this->authorize('manage', AdCampaign::class);

        return AdCampaignResource::collection(
            $action->execute($request->user(), max(1, min(50, (int) $request->integer('per_page', 20)))),
        );
    }

    public function store(CreateAdRequest $request, CreateAd $action): JsonResponse
    {
        $this->authorize('create', AdCampaign::class);

        $validated = $request->validated();
        $campaign = $action->execute(
            $request->user(),
            new CreateAdCampaignData(
                title: (string) $validated['title'],
                placement: (string) $validated['placement'],
                body: $validated['body'] ?? null,
                mediaUrl: $validated['media_url'] ?? null,
                mediaType: (string) ($validated['media_type'] ?? 'image'),
                ctaLabel: $validated['cta_label'] ?? null,
                ctaUrl: $validated['cta_url'] ?? null,
                targetType: (string) ($validated['target_type'] ?? 'all'),
                targetPayload: $validated['target_payload'] ?? null,
                status: (string) ($validated['status'] ?? 'draft'),
                priority: (int) ($validated['priority'] ?? 0),
                budgetImpressions: isset($validated['budget_impressions']) ? (int) $validated['budget_impressions'] : null,
                budgetClicks: isset($validated['budget_clicks']) ? (int) $validated['budget_clicks'] : null,
                startsAt: $validated['starts_at'] ?? null,
                endsAt: $validated['ends_at'] ?? null,
                metadata: $validated['metadata'] ?? null,
            ),
        );

        return response()->json([
            'data' => (new AdCampaignResource($campaign))->resolve($request),
        ], 201);
    }

    public function show(Request $request, string $ad, ShowAd $action): JsonResponse
    {
        $campaign = $action->execute($request->user(), $ad);
        $this->authorize('view', $campaign);

        return response()->json([
            'data' => (new AdCampaignResource($campaign))->resolve($request),
        ]);
    }

    public function update(
        UpdateAdRequest $request,
        string $ad,
        UpdateAd $action,
        AdRepository $ads,
    ): JsonResponse {
        $campaign = $this->ownedOrFail($request, $ads, $ad);
        $this->authorize('update', $campaign);

        $validated = $request->validated();
        $campaign = $action->execute(
            $request->user(),
            $ad,
            new UpdateAdCampaignData(
                title: $validated['title'] ?? null,
                body: array_key_exists('body', $validated) ? $validated['body'] : null,
                placement: $validated['placement'] ?? null,
                mediaUrl: array_key_exists('media_url', $validated) ? $validated['media_url'] : null,
                mediaType: $validated['media_type'] ?? null,
                ctaLabel: array_key_exists('cta_label', $validated) ? $validated['cta_label'] : null,
                ctaUrl: array_key_exists('cta_url', $validated) ? $validated['cta_url'] : null,
                targetType: $validated['target_type'] ?? null,
                targetPayload: array_key_exists('target_payload', $validated) ? $validated['target_payload'] : null,
                priority: isset($validated['priority']) ? (int) $validated['priority'] : null,
                budgetImpressions: array_key_exists('budget_impressions', $validated)
                    ? ($validated['budget_impressions'] !== null ? (int) $validated['budget_impressions'] : null)
                    : null,
                budgetClicks: array_key_exists('budget_clicks', $validated)
                    ? ($validated['budget_clicks'] !== null ? (int) $validated['budget_clicks'] : null)
                    : null,
                startsAt: array_key_exists('starts_at', $validated) ? $validated['starts_at'] : null,
                endsAt: array_key_exists('ends_at', $validated) ? $validated['ends_at'] : null,
                metadata: array_key_exists('metadata', $validated) ? $validated['metadata'] : null,
                clearStartsAt: array_key_exists('starts_at', $validated) && $validated['starts_at'] === null,
                clearEndsAt: array_key_exists('ends_at', $validated) && $validated['ends_at'] === null,
            ),
        );

        return response()->json([
            'data' => (new AdCampaignResource($campaign))->resolve($request),
        ]);
    }

    public function pause(Request $request, string $ad, PauseAd $action, AdRepository $ads): JsonResponse
    {
        $campaign = $this->ownedOrFail($request, $ads, $ad);
        $this->authorize('update', $campaign);
        $campaign = $action->execute($request->user(), $ad);

        return response()->json([
            'data' => (new AdCampaignResource($campaign))->resolve($request),
        ]);
    }

    public function resume(Request $request, string $ad, ResumeAd $action, AdRepository $ads): JsonResponse
    {
        $campaign = $this->ownedOrFail($request, $ads, $ad);
        $this->authorize('update', $campaign);
        $campaign = $action->execute($request->user(), $ad);

        return response()->json([
            'data' => (new AdCampaignResource($campaign))->resolve($request),
        ]);
    }

    public function destroy(Request $request, string $ad, DeleteAd $action, AdRepository $ads): JsonResponse
    {
        $campaign = $this->ownedOrFail($request, $ads, $ad);
        $this->authorize('delete', $campaign);
        $action->execute($request->user(), $ad);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function track(TrackAdEventRequest $request, string $ad, TrackAdEvent $action): JsonResponse
    {
        $this->authorize('track', AdCampaign::class);

        $event = $action->execute(
            $request->user(),
            $ad,
            new RecordAdEventData(
                eventType: (string) $request->validated('event_type'),
                contextId: $request->validated('context_id'),
                metadata: $request->validated('metadata'),
            ),
        );

        return response()->json([
            'data' => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'ad_campaign_id' => $event->ad_campaign_id,
                'created_at' => $event->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function analytics(Request $request, string $ad, GetAdAnalytics $action, AdRepository $ads): JsonResponse
    {
        $campaign = $this->ownedOrFail($request, $ads, $ad);
        $this->authorize('update', $campaign);

        return response()->json([
            'data' => $action->execute($request->user(), $ad),
        ]);
    }

    private function ownedOrFail(Request $request, AdRepository $ads, string $ad): AdCampaign
    {
        $campaign = $ads->findOwned($request->user(), $ad);
        if ($campaign === null) {
            throw new NotFoundHttpException('Ad campaign not found.');
        }

        return $campaign;
    }
}
