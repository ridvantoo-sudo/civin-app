<?php

namespace Tests\Feature\GameAnalytics;

use App\Features\GameAnalytics\DTO\AnalyticsFilterData;
use App\Features\GameAnalytics\DTO\ExportRequestData;
use App\Features\GameAnalytics\Models\AnalyticsDaily;
use App\Features\GameAnalytics\Services\ExportService;
use Carbon\CarbonImmutable;

final class AnalyticsExportTest extends GameAnalyticsTestCase
{
    public function test_admin_can_export_csv_xls_and_pdf(): void
    {
        $this->actingAdmin();

        AnalyticsDaily::query()->create([
            'stat_date' => now()->toDateString(),
            'game_slug' => 'ludo',
            'country_code' => 'DE',
            'device_platform' => 'android',
            'games_played' => 8,
            'unique_players' => 3,
            'dau' => 3,
            'revenue' => 90,
            'coins_wagered' => 120,
            'coins_won' => 30,
        ]);

        foreach (['csv', 'xls', 'pdf'] as $format) {
            $response = $this->getJson('/api/v1/analytics/export?format='.$format.'&report_type=overview');
            $response->assertOk()->assertJsonStructure([
                'data' => ['filename', 'mime_type', 'content', 'encoding'],
            ]);
            $this->assertNotEmpty($response->json('data.content'));
        }
    }

    public function test_non_admin_cannot_export(): void
    {
        $this->actingUser(['is_admin' => false]);

        $this->getJson('/api/v1/analytics/export?format=csv&report_type=overview')
            ->assertForbidden();
    }

    public function test_export_service_builds_native_formats(): void
    {
        AnalyticsDaily::query()->create([
            'stat_date' => now()->toDateString(),
            'game_slug' => 'treasure-box',
            'games_played' => 2,
            'revenue' => 15,
            'dau' => 1,
        ]);

        $filters = new AnalyticsFilterData(
            from: CarbonImmutable::now()->subDay(),
            to: CarbonImmutable::now()->endOfDay(),
        );

        $csv = app(ExportService::class)->export(new ExportRequestData('csv', 'overview', $filters));
        $xls = app(ExportService::class)->export(new ExportRequestData('xls', 'overview', $filters));
        $pdf = app(ExportService::class)->export(new ExportRequestData('pdf', 'overview', $filters));

        $this->assertStringContainsString('games_played', $csv['contents']);
        $this->assertStringContainsString('Workbook', $xls['contents']);
        $this->assertStringStartsWith('%PDF', $pdf['contents']);
    }
}
