<?php

namespace App\Features\Ads\Repositories\Eloquent;

use App\Features\Ads\DTOs\CreateAdCampaignData;
use App\Features\Ads\DTOs\RecordAdEventData;
use App\Features\Ads\DTOs\UpdateAdCampaignData;
use App\Features\Ads\Models\AdCampaign;
use App\Features\Ads\Models\AdEvent;
use App\Features\Ads\Repositories\Contracts\AdRepository;
use App\Features\Levels\Models\UserLevelProgress;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentAdRepository implements AdRepository
{
    public function find(string $id): ?AdCampaign
    {
        return AdCampaign::query()->find($id);
    }

    public function findOwned(User $user, string $id): ?AdCampaign
    {
        return AdCampaign::query()
            ->whereKey($id)
            ->where('owner_user_id', $user->getKey())
            ->first();
    }

    public function servePlacement(
        string $placement,
        int $limit,
        ?User $viewer = null,
        ?string $contextId = null,
    ): Collection {
        $now = now();

        $query = AdCampaign::query()
            ->where('placement', $placement)
            ->where('status', AdCampaign::STATUS_ACTIVE)
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function (Builder $builder): void {
                $builder->whereNull('budget_impressions')
                    ->orWhereColumn('impressions_count', '<', 'budget_impressions');
            })
            ->where(function (Builder $builder): void {
                $builder->whereNull('budget_clicks')
                    ->orWhereColumn('clicks_count', '<', 'budget_clicks');
            })
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->limit(max($limit * 3, $limit));

        $campaigns = $query->get()->filter(
            fn (AdCampaign $campaign): bool => $this->matchesTargeting($campaign, $viewer, $contextId),
        )->take($limit)->values();

        return $campaigns;
    }

    public function paginateOwned(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return AdCampaign::query()
            ->where('owner_user_id', $user->getKey())
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function countActiveOwned(User $user): int
    {
        return AdCampaign::query()
            ->where('owner_user_id', $user->getKey())
            ->where('status', AdCampaign::STATUS_ACTIVE)
            ->count();
    }

    public function create(User $owner, CreateAdCampaignData $data, bool $asAdmin = false): AdCampaign
    {
        return AdCampaign::query()->create([
            'owner_user_id' => $asAdmin ? null : $owner->getKey(),
            'is_admin_campaign' => $asAdmin,
            'title' => $data->title,
            'body' => $data->body,
            'placement' => $data->placement,
            'media_url' => $data->mediaUrl,
            'media_type' => $data->mediaType,
            'cta_label' => $data->ctaLabel,
            'cta_url' => $data->ctaUrl,
            'target_type' => $data->targetType,
            'target_payload' => $data->targetPayload,
            'status' => $data->status,
            'priority' => $data->priority,
            'budget_impressions' => $data->budgetImpressions,
            'budget_clicks' => $data->budgetClicks,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'metadata' => $data->metadata,
        ]);
    }

    public function update(AdCampaign $campaign, UpdateAdCampaignData $data): AdCampaign
    {
        $payload = array_filter([
            'title' => $data->title,
            'body' => $data->body,
            'placement' => $data->placement,
            'media_url' => $data->mediaUrl,
            'media_type' => $data->mediaType,
            'cta_label' => $data->ctaLabel,
            'cta_url' => $data->ctaUrl,
            'target_type' => $data->targetType,
            'target_payload' => $data->targetPayload,
            'priority' => $data->priority,
            'budget_impressions' => $data->budgetImpressions,
            'budget_clicks' => $data->budgetClicks,
            'metadata' => $data->metadata,
        ], static fn (mixed $value): bool => $value !== null);

        if ($data->clearStartsAt) {
            $payload['starts_at'] = null;
        } elseif ($data->startsAt !== null) {
            $payload['starts_at'] = $data->startsAt;
        }

        if ($data->clearEndsAt) {
            $payload['ends_at'] = null;
        } elseif ($data->endsAt !== null) {
            $payload['ends_at'] = $data->endsAt;
        }

        $campaign->fill($payload);
        $campaign->save();

        return $campaign->fresh();
    }

    public function setStatus(AdCampaign $campaign, string $status): AdCampaign
    {
        $campaign->status = $status;
        $campaign->save();

        return $campaign->fresh();
    }

    public function delete(AdCampaign $campaign): void
    {
        $campaign->delete();
    }

    public function recordEvent(AdCampaign $campaign, ?User $viewer, RecordAdEventData $data): AdEvent
    {
        return DB::transaction(function () use ($campaign, $viewer, $data): AdEvent {
            $event = AdEvent::query()->create([
                'ad_campaign_id' => $campaign->getKey(),
                'user_id' => $viewer?->getKey(),
                'event_type' => $data->eventType,
                'placement' => $campaign->placement,
                'context_id' => $data->contextId,
                'metadata' => $data->metadata,
                'created_at' => now(),
            ]);

            if ($data->eventType === AdEvent::TYPE_IMPRESSION) {
                AdCampaign::query()->whereKey($campaign->getKey())->increment('impressions_count');
            }

            if ($data->eventType === AdEvent::TYPE_CLICK) {
                AdCampaign::query()->whereKey($campaign->getKey())->increment('clicks_count');
            }

            return $event;
        });
    }

    private function matchesTargeting(AdCampaign $campaign, ?User $viewer, ?string $contextId): bool
    {
        return match ($campaign->target_type) {
            AdCampaign::TARGET_ALL => true,
            AdCampaign::TARGET_COUNTRY => $this->matchesCountry($campaign, $viewer),
            AdCampaign::TARGET_LEVEL_RANGE => $this->matchesLevelRange($campaign, $viewer),
            AdCampaign::TARGET_LIVE_ROOM,
            AdCampaign::TARGET_VOICE_ROOM,
            AdCampaign::TARGET_FAMILY => $this->matchesContext($campaign, $contextId),
            default => true,
        };
    }

    private function matchesCountry(AdCampaign $campaign, ?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        $countries = (array) ($campaign->target_payload['country_codes'] ?? []);
        if ($countries === []) {
            return true;
        }

        $code = strtoupper((string) ($viewer->profile?->country?->alpha2 ?? ''));

        return $code !== '' && in_array($code, array_map('strtoupper', $countries), true);
    }

    private function matchesLevelRange(AdCampaign $campaign, ?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        $min = (int) ($campaign->target_payload['min_level'] ?? 1);
        $max = (int) ($campaign->target_payload['max_level'] ?? 100);
        $level = (int) (UserLevelProgress::query()
            ->where('user_id', $viewer->getKey())
            ->value('level') ?? $viewer->profile?->level ?? 1);

        return $level >= $min && $level <= $max;
    }

    private function matchesContext(AdCampaign $campaign, ?string $contextId): bool
    {
        if ($contextId === null || $contextId === '') {
            return false;
        }

        $ids = (array) ($campaign->target_payload['ids'] ?? []);
        if ($ids === [] && isset($campaign->target_payload['id'])) {
            $ids = [(string) $campaign->target_payload['id']];
        }

        if ($ids === []) {
            return true;
        }

        return in_array($contextId, array_map('strval', $ids), true);
    }
}
