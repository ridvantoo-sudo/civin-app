<?php

namespace App\Features\Growth\Services;

use App\Features\Devices\Models\Device;
use App\Features\Growth\Contracts\PushGateway;
use App\Features\Growth\DTOs\CreateCampaignData;
use App\Features\Growth\Jobs\DispatchCampaignJob;
use App\Features\Growth\Mail\CampaignMail;
use App\Features\Growth\Models\CampaignDelivery;
use App\Features\Growth\Models\GrowthCampaign;
use App\Features\Growth\Support\CampaignChannel;
use App\Features\Growth\Support\CampaignStatus;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final readonly class CampaignService
{
    public function __construct(private PushGateway $push) {}

    public function create(User $creator, CreateCampaignData $data): GrowthCampaign
    {
        $this->ensureEligible($creator);

        if (! in_array($data->channel, CampaignChannel::all(), true)) {
            throw ValidationException::withMessages(['channel' => 'Invalid campaign channel.']);
        }

        if (trim($data->body) === '') {
            throw ValidationException::withMessages(['body' => 'Campaign body is required.']);
        }

        $status = $data->scheduledAt !== null && $data->scheduledAt > now()
            ? CampaignStatus::SCHEDULED
            : CampaignStatus::DRAFT;

        return GrowthCampaign::query()->create([
            'name' => $data->name,
            'channel' => $data->channel,
            'status' => $status,
            'subject' => $data->subject,
            'body' => $data->body,
            'action_url' => $data->actionUrl,
            'audience' => $data->audience ?? ['type' => 'all'],
            'scheduled_at' => $data->scheduledAt,
            'created_by' => $creator->getKey(),
            'metadata' => $data->metadata,
        ]);
    }

    public function dispatchNow(User $actor, GrowthCampaign $campaign): GrowthCampaign
    {
        $this->ensureOwner($actor, $campaign);

        if (! $campaign->isDispatchable()) {
            throw ValidationException::withMessages(['campaign' => 'Campaign cannot be dispatched in its current status.']);
        }

        $campaign->forceFill([
            'status' => CampaignStatus::SENDING,
            'started_at' => now(),
        ])->save();

        DispatchCampaignJob::dispatch($campaign->getKey());

        return $campaign->fresh() ?? $campaign;
    }

    public function cancel(User $actor, GrowthCampaign $campaign): GrowthCampaign
    {
        $this->ensureOwner($actor, $campaign);

        if (in_array($campaign->status, [CampaignStatus::SENT, CampaignStatus::CANCELLED], true)) {
            throw ValidationException::withMessages(['campaign' => 'Campaign cannot be cancelled.']);
        }

        $campaign->forceFill(['status' => CampaignStatus::CANCELLED])->save();

        return $campaign;
    }

    public function mine(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return GrowthCampaign::query()
            ->where('created_by', $user->getKey())
            ->latest()
            ->paginate(max(1, min($perPage, 100)));
    }

    /**
     * Process deliveries for a campaign (called from the queued job).
     */
    public function process(GrowthCampaign $campaign): void
    {
        if ($campaign->status === CampaignStatus::CANCELLED) {
            return;
        }

        $campaign->forceFill(['status' => CampaignStatus::SENDING, 'started_at' => $campaign->started_at ?? now()])->save();

        $channels = CampaignChannel::deliveryChannels($campaign->channel);
        $users = $this->resolveAudience($campaign);
        $campaign->forceFill(['targeted_count' => $users->count()])->save();

        $sent = 0;
        $failed = 0;
        $batch = max(1, (int) config('growth.campaigns.batch_size', 200));

        foreach ($users->chunk($batch) as $chunk) {
            foreach ($chunk as $user) {
                foreach ($channels as $channel) {
                    $result = $this->deliver($campaign, $user, $channel);
                    if ($result === CampaignDelivery::STATUS_SENT) {
                        $sent++;
                    } elseif ($result === CampaignDelivery::STATUS_FAILED) {
                        $failed++;
                    }
                }
            }
        }

        $campaign->forceFill([
            'status' => CampaignStatus::SENT,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'completed_at' => now(),
        ])->save();
    }

    /**
     * Dispatch due scheduled campaigns (scheduler entrypoint).
     */
    public function dispatchDue(): int
    {
        $due = GrowthCampaign::query()
            ->where('status', CampaignStatus::SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->limit(50)
            ->get();

        foreach ($due as $campaign) {
            $campaign->forceFill(['status' => CampaignStatus::SENDING, 'started_at' => now()])->save();
            DispatchCampaignJob::dispatch($campaign->getKey());
        }

        return $due->count();
    }

    private function deliver(GrowthCampaign $campaign, User $user, string $channel): string
    {
        $existing = CampaignDelivery::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('user_id', $user->getKey())
            ->where('channel', $channel)
            ->first();

        if ($existing !== null) {
            return $existing->status;
        }

        $delivery = CampaignDelivery::query()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $user->getKey(),
            'channel' => $channel,
            'status' => CampaignDelivery::STATUS_PENDING,
        ]);

        try {
            if ($channel === CampaignChannel::EMAIL) {
                if (! $user->email) {
                    $delivery->forceFill(['status' => CampaignDelivery::STATUS_SKIPPED, 'error_message' => 'No email'])->save();

                    return CampaignDelivery::STATUS_SKIPPED;
                }

                Mail::to($user->email)->send(new CampaignMail($campaign));
                $delivery->forceFill([
                    'status' => CampaignDelivery::STATUS_SENT,
                    'sent_at' => now(),
                    'provider_reference' => 'mail:'.$user->email,
                ])->save();

                return CampaignDelivery::STATUS_SENT;
            }

            // Push: send to all devices with a push_token.
            $tokens = Device::query()
                ->where('user_id', $user->getKey())
                ->whereNotNull('push_token')
                ->pluck('push_token')
                ->filter()
                ->unique()
                ->values();

            if ($tokens->isEmpty()) {
                $delivery->forceFill(['status' => CampaignDelivery::STATUS_SKIPPED, 'error_message' => 'No push token'])->save();

                return CampaignDelivery::STATUS_SKIPPED;
            }

            $ok = false;
            $ref = null;
            $error = null;
            foreach ($tokens as $token) {
                $result = $this->push->send(
                    (string) $token,
                    (string) ($campaign->subject ?: $campaign->name),
                    $campaign->body,
                    $campaign->action_url,
                    ['campaign_id' => (string) $campaign->getKey()],
                );
                if ($result['ok']) {
                    $ok = true;
                    $ref = $result['reference'];
                } else {
                    $error = $result['error'];
                }
            }

            $delivery->forceFill([
                'status' => $ok ? CampaignDelivery::STATUS_SENT : CampaignDelivery::STATUS_FAILED,
                'provider_reference' => $ref,
                'error_message' => $ok ? null : $error,
                'sent_at' => $ok ? now() : null,
            ])->save();

            return $ok ? CampaignDelivery::STATUS_SENT : CampaignDelivery::STATUS_FAILED;
        } catch (\Throwable $exception) {
            $delivery->forceFill([
                'status' => CampaignDelivery::STATUS_FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 500),
            ])->save();

            return CampaignDelivery::STATUS_FAILED;
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveAudience(GrowthCampaign $campaign): Collection
    {
        $audience = $campaign->audience ?? ['type' => 'all'];
        $type = (string) ($audience['type'] ?? 'all');

        $query = User::query()
            ->where('is_guest', false)
            ->where('status', 'active');

        if ($type === 'user_ids' && ! empty($audience['user_ids']) && is_array($audience['user_ids'])) {
            $query->whereIn('id', $audience['user_ids']);
        }

        if ($type === 'country' && ! empty($audience['country_id'])) {
            $query->whereHas('profile', function (Builder $builder) use ($audience): void {
                $builder->where('country_id', $audience['country_id']);
            });
        }

        return $query->limit(10000)->get();
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can manage campaigns.');
        }
    }

    private function ensureOwner(User $user, GrowthCampaign $campaign): void
    {
        $this->ensureEligible($user);

        if ($campaign->created_by !== $user->getKey()) {
            throw new AuthorizationException('You do not own this campaign.');
        }
    }
}
