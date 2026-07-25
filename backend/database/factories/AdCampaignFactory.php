<?php

namespace Database\Factories;

use App\Features\Ads\Models\AdCampaign;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdCampaign> */
class AdCampaignFactory extends Factory
{
    protected $model = AdCampaign::class;

    public function definition(): array
    {
        return [
            'owner_user_id' => null,
            'is_admin_campaign' => true,
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(8),
            'placement' => AdCampaign::PLACEMENT_FEED_POST,
            'media_url' => fake()->imageUrl(1200, 630),
            'media_type' => AdCampaign::MEDIA_IMAGE,
            'cta_label' => 'Learn more',
            'cta_url' => fake()->url(),
            'target_type' => AdCampaign::TARGET_ALL,
            'target_payload' => null,
            'status' => AdCampaign::STATUS_ACTIVE,
            'priority' => fake()->numberBetween(0, 20),
            'budget_impressions' => null,
            'budget_clicks' => null,
            'impressions_count' => 0,
            'clicks_count' => 0,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(7),
            'reviewed_at' => now(),
            'reviewed_by' => null,
            'rejection_reason' => null,
            'metadata' => null,
        ];
    }

    public function forUser(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'owner_user_id' => $user?->getKey() ?? User::factory(),
            'is_admin_campaign' => false,
            'status' => AdCampaign::STATUS_ACTIVE,
        ]);
    }

    public function feedPost(): static
    {
        return $this->state(fn (): array => [
            'placement' => AdCampaign::PLACEMENT_FEED_POST,
        ]);
    }

    public function liveBanner(): static
    {
        return $this->state(fn (): array => [
            'placement' => AdCampaign::PLACEMENT_LIVE_BANNER,
        ]);
    }

    public function chatBanner(): static
    {
        return $this->state(fn (): array => [
            'placement' => AdCampaign::PLACEMENT_CHAT_BANNER,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (): array => [
            'status' => AdCampaign::STATUS_PAUSED,
        ]);
    }
}
