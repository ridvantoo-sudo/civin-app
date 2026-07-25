<?php

namespace App\Features\GameAnalytics\Services;

use App\Features\GameAnalytics\DTO\AnalyticsFilterData;
use App\Features\GameAnalytics\DTO\ExportRequestData;
use App\Features\GameAnalytics\Models\ScheduledReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ScheduledReportService
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly AnalyticsScheduler $scheduler,
    ) {}

    public function processDue(): int
    {
        $reports = ScheduledReport::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->limit(50)
            ->get();

        $processed = 0;
        foreach ($reports as $report) {
            try {
                $this->run($report);
                $this->scheduler->markReportRan($report, 'success');
                $processed++;
            } catch (Throwable $exception) {
                $this->scheduler->markReportRan($report, 'failed', $exception->getMessage());
            }
        }

        return $processed;
    }

    public function run(ScheduledReport $report): void
    {
        $filters = $report->filters ?? [];
        $filterData = new AnalyticsFilterData(
            from: isset($filters['from'])
                ? CarbonImmutable::parse((string) $filters['from'])
                : CarbonImmutable::now()->subDays(7)->startOfDay(),
            to: isset($filters['to'])
                ? CarbonImmutable::parse((string) $filters['to'])
                : CarbonImmutable::now()->endOfDay(),
            gameSlug: $filters['game_slug'] ?? null,
            countryCode: $filters['country_code'] ?? null,
            devicePlatform: $filters['device_platform'] ?? null,
            limit: (int) ($filters['limit'] ?? 100),
        );

        $formats = $report->formats ?: config('game_analytics.scheduled_reports.default_formats', ['csv']);
        $attachments = [];
        foreach ($formats as $format) {
            $export = $this->exportService->export(new ExportRequestData(
                format: (string) $format,
                reportType: $report->report_type,
                filters: $filterData,
            ));
            $attachments[] = $export;
        }

        foreach ($report->recipients as $recipient) {
            if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            Mail::raw(
                "Civin analytics scheduled report: {$report->name}",
                function ($message) use ($recipient, $report, $attachments): void {
                    $message->to($recipient)->subject('[Civin Analytics] '.$report->name);
                    foreach ($attachments as $attachment) {
                        $message->attachData(
                            $attachment['contents'],
                            $attachment['filename'],
                            ['mime' => $attachment['mime']],
                        );
                    }
                },
            );
        }
    }
}
