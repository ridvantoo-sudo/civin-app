<?php

namespace App\Features\Ads\Services;

use App\Features\Ads\DTOs\CreateAdCampaignData;
use App\Features\Ads\DTOs\RecordAdEventData;
use App\Features\Ads\DTOs\UpdateAdCampaignData;
use App\Features\Ads\Models\AdCampaign;
use App\Features\Ads\Models\AdEvent;
use App\Features\Ads\Repositories\Contracts\AdRepository;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final readonly class AdService
{
    public function __construct(
        private AdRepository $ads,
        private AdEligibilityService $eligibility,
    ) {}

    /** @return array{eligible: bool, min_level: int, user_level: int, is_svip: bool, allow_svip: bool, reason: string|null} */
    public function eligibility(User $user): array
    {
        return $this->eligibility->eligibilityPayload($user);
    }

    /** @return Collection<int, AdCampaign> */
    public function serve(string $placement, ?User $viewer = null, ?string $contextId = null, ?int $limit = null): Collection
    {
        if (! in_array($placement, AdCampaign::placements(), true)) {
            throw ValidationException::withMessages([
                'placement' => 'Invalid ad placement.',
            ]);
        }

        $default = (int) config('ads.default_serve_limit', 5);
        $max = (int) config('ads.max_serve_limit', 20);
        $resolvedLimit = max(1, min($limit ?? $default, $max));

        return $this->ads->servePlacement($placement, $resolvedLimit, $viewer, $contextId);
    }

    public function mine(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureCanManage($user);

        return $this->ads->paginateOwned($user, $perPage);
    }

    public function show(User $user, string $id): AdCampaign
    {
        $campaign = $this->ads->find($id);
        if ($campaign === null) {
            throw ValidationException::withMessages([
                'ad' => 'Ad campaign not found.',
            ]);
        }

        if ($campaign->isOwnedBy($user) || $campaign->isRunnableNow() || $user->is_admin) {
            return $campaign;
        }

        throw new AuthorizationException('You cannot view this ad campaign.');
    }

    public function create(User $user, CreateAdCampaignData $data): AdCampaign
    {
        $this->ensureCanManage($user);
        $this->assertPlacement($data->placement);
        $this->assertTarget($data->targetType);
        $this->assertMediaType($data->mediaType);
        $this->assertStatusForCreate($data->status);
        $this->assertSchedule($data->startsAt, $data->endsAt);

        if ($data->status === AdCampaign::STATUS_ACTIVE) {
            $this->ensureActiveQuota($user);
        }

        return $this->ads->create($user, $data);
    }

    public function update(User $user, string $id, UpdateAdCampaignData $data): AdCampaign
    {
        $this->ensureCanManage($user);
        $campaign = $this->requireOwned($user, $id);

        if ($data->placement !== null) {
            $this->assertPlacement($data->placement);
        }
        if ($data->targetType !== null) {
            $this->assertTarget($data->targetType);
        }
        if ($data->mediaType !== null) {
            $this->assertMediaType($data->mediaType);
        }

        $startsAt = $data->clearStartsAt ? null : ($data->startsAt ?? $campaign->starts_at?->toIso8601String());
        $endsAt = $data->clearEndsAt ? null : ($data->endsAt ?? $campaign->ends_at?->toIso8601String());
        $this->assertSchedule($startsAt, $endsAt);

        return $this->ads->update($campaign, $data);
    }

    public function pause(User $user, string $id): AdCampaign
    {
        $this->ensureCanManage($user);
        $campaign = $this->requireOwned($user, $id);

        if (! in_array($campaign->status, [AdCampaign::STATUS_ACTIVE, AdCampaign::STATUS_DRAFT], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only active or draft ads can be paused.',
            ]);
        }

        return $this->ads->setStatus($campaign, AdCampaign::STATUS_PAUSED);
    }

    public function resume(User $user, string $id): AdCampaign
    {
        $this->ensureCanManage($user);
        $campaign = $this->requireOwned($user, $id);

        if ($campaign->status !== AdCampaign::STATUS_PAUSED) {
            throw ValidationException::withMessages([
                'status' => 'Only paused ads can be resumed.',
            ]);
        }

        $this->ensureActiveQuota($user);

        return $this->ads->setStatus($campaign, AdCampaign::STATUS_ACTIVE);
    }

    public function delete(User $user, string $id): void
    {
        $this->ensureCanManage($user);
        $campaign = $this->requireOwned($user, $id);
        $this->ads->delete($campaign);
    }

    public function track(User $user, string $id, RecordAdEventData $data): AdEvent
    {
        if (! in_array($data->eventType, AdEvent::types(), true)) {
            throw ValidationException::withMessages([
                'event_type' => 'Invalid ad event type.',
            ]);
        }

        $campaign = $this->ads->find($id);
        if ($campaign === null) {
            throw ValidationException::withMessages([
                'ad' => 'Ad campaign not found.',
            ]);
        }

        return $this->ads->recordEvent($campaign, $user, $data);
    }

    /** @return array{impressions: int, clicks: int, dismissals: int, rewards: int, ctr: float, events_by_day: list<array{date: string, impressions: int, clicks: int}>} */
    public function analytics(User $user, string $id): array
    {
        $this->ensureCanManage($user);
        $campaign = $this->requireOwned($user, $id);
        $campaign->loadMissing('events');

        $impressions = (int) $campaign->events->where('event_type', AdEvent::TYPE_IMPRESSION)->count();
        $clicks = (int) $campaign->events->where('event_type', AdEvent::TYPE_CLICK)->count();
        $dismissals = (int) $campaign->events->where('event_type', AdEvent::TYPE_DISMISS)->count();
        $rewards = (int) $campaign->events->where('event_type', AdEvent::TYPE_REWARD_COMPLETE)->count();

        $byDay = $campaign->events
            ->groupBy(fn (AdEvent $event): string => $event->created_at?->toDateString() ?? '')
            ->filter(fn ($_, string $date): bool => $date !== '')
            ->map(function ($events, string $date): array {
                return [
                    'date' => $date,
                    'impressions' => $events->where('event_type', AdEvent::TYPE_IMPRESSION)->count(),
                    'clicks' => $events->where('event_type', AdEvent::TYPE_CLICK)->count(),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        return [
            'impressions' => max($impressions, (int) $campaign->impressions_count),
            'clicks' => max($clicks, (int) $campaign->clicks_count),
            'dismissals' => $dismissals,
            'rewards' => $rewards,
            'ctr' => $campaign->clickThroughRate(),
            'events_by_day' => $byDay,
        ];
    }

    private function ensureCanManage(User $user): void
    {
        if (! $this->eligibility->canCreateAds($user)) {
            $payload = $this->eligibility->eligibilityPayload($user);
            throw new AuthorizationException($payload['reason'] ?? 'Ad creation is locked for your account.');
        }
    }

    private function requireOwned(User $user, string $id): AdCampaign
    {
        $campaign = $this->ads->findOwned($user, $id);
        if ($campaign === null) {
            throw ValidationException::withMessages([
                'ad' => 'Ad campaign not found.',
            ]);
        }

        return $campaign;
    }

    private function ensureActiveQuota(User $user): void
    {
        $max = (int) config('ads.max_active_campaigns_per_user', 5);
        if ($this->ads->countActiveOwned($user) >= $max) {
            throw ValidationException::withMessages([
                'status' => sprintf('You can run at most %d active ads at once.', $max),
            ]);
        }
    }

    private function assertPlacement(string $placement): void
    {
        if (! in_array($placement, AdCampaign::placements(), true)) {
            throw ValidationException::withMessages([
                'placement' => 'Invalid ad placement.',
            ]);
        }
    }

    private function assertTarget(string $targetType): void
    {
        if (! in_array($targetType, AdCampaign::targetTypes(), true)) {
            throw ValidationException::withMessages([
                'target_type' => 'Invalid targeting type.',
            ]);
        }
    }

    private function assertMediaType(string $mediaType): void
    {
        if (! in_array($mediaType, AdCampaign::mediaTypes(), true)) {
            throw ValidationException::withMessages([
                'media_type' => 'Invalid media type.',
            ]);
        }
    }

    private function assertStatusForCreate(string $status): void
    {
        $allowed = [
            AdCampaign::STATUS_DRAFT,
            AdCampaign::STATUS_ACTIVE,
            AdCampaign::STATUS_PAUSED,
        ];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid status for new ads.',
            ]);
        }
    }

    private function assertSchedule(?string $startsAt, ?string $endsAt): void
    {
        if ($startsAt === null || $endsAt === null) {
            return;
        }

        if (strtotime($startsAt) !== false && strtotime($endsAt) !== false && strtotime($endsAt) < strtotime($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The end time must be after the start time.',
            ]);
        }
    }
}
