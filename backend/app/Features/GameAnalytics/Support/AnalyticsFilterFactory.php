<?php

namespace App\Features\GameAnalytics\Support;

use App\Features\GameAnalytics\DTO\AnalyticsFilterData;
use App\Features\GameAnalytics\Requests\AnalyticsFilterRequest;
use Carbon\CarbonImmutable;

final class AnalyticsFilterFactory
{
    public static function fromRequest(AnalyticsFilterRequest $request): AnalyticsFilterData
    {
        return new AnalyticsFilterData(
            from: $request->filled('from')
                ? CarbonImmutable::parse((string) $request->input('from'))->startOfDay()
                : CarbonImmutable::now()->subDays(30)->startOfDay(),
            to: $request->filled('to')
                ? CarbonImmutable::parse((string) $request->input('to'))->endOfDay()
                : CarbonImmutable::now()->endOfDay(),
            gameSlug: $request->input('game_slug'),
            countryCode: $request->input('country_code'),
            devicePlatform: $request->input('device_platform'),
            roomType: $request->input('room_type'),
            limit: (int) $request->input('limit', 50),
        );
    }
}
