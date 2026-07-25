<?php

namespace App\Features\SocialEngagement;

use App\Features\SocialEngagement\Services\AchievementService;
use App\Features\SocialEngagement\Services\CheckInService;
use App\Features\SocialEngagement\Services\MentorshipService;
use Illuminate\Support\ServiceProvider;

/**
 * Social engagement: mentorship, achievements catalog, and daily check-in
 * streaks. Distinct from Levels (XP/badges/daily tasks) and Challenges.
 * Credits wallet rewards via applyGrowthReward.
 */
final class SocialEngagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/social_engagement.php', 'social_engagement');

        $this->app->singleton(AchievementService::class);
        $this->app->singleton(CheckInService::class);
        $this->app->singleton(MentorshipService::class);
    }
}
