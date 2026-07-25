<?php

namespace App\Features\GameAnalytics\Analytics;

/**
 * Marker namespace for analytics pipeline components.
 * Collector / aggregator live under Services and are bound via GameAnalyticsServiceProvider.
 */
final class Pipeline
{
    public const QUEUE = 'analytics';
}
