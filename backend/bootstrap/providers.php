<?php

use App\Features\Ai\AiServiceProvider;
use App\Features\CreatorTools\CreatorToolsServiceProvider;
use App\Features\GameAnalytics\GameAnalyticsServiceProvider;
use App\Features\Global\GlobalServiceProvider;
use App\Features\Growth\GrowthServiceProvider;
use App\Features\Operations\OperationsServiceProvider;
use App\Features\Payments\PaymentsServiceProvider;
use App\Features\Security\SecurityServiceProvider;
use App\Features\SocialEngagement\SocialEngagementServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    AiServiceProvider::class,
    CreatorToolsServiceProvider::class,
    GameAnalyticsServiceProvider::class,
    GlobalServiceProvider::class,
    GrowthServiceProvider::class,
    OperationsServiceProvider::class,
    PaymentsServiceProvider::class,
    SecurityServiceProvider::class,
    SocialEngagementServiceProvider::class,
];
