<?php

namespace App\Features\GameAnalytics\Controllers;

use App\Features\GameAnalytics\Actions\ExportAnalytics;
use App\Features\GameAnalytics\Actions\GetAnalyticsAgencies;
use App\Features\GameAnalytics\Actions\GetAnalyticsGames;
use App\Features\GameAnalytics\Actions\GetAnalyticsLive;
use App\Features\GameAnalytics\Actions\GetAnalyticsOverview;
use App\Features\GameAnalytics\Actions\GetAnalyticsPlayers;
use App\Features\GameAnalytics\Actions\GetAnalyticsRetention;
use App\Features\GameAnalytics\Actions\GetAnalyticsRevenue;
use App\Features\GameAnalytics\Actions\GetAnalyticsVip;
use App\Features\GameAnalytics\DTO\AnalyticsFilterData;
use App\Features\GameAnalytics\DTO\ExportRequestData;
use App\Features\GameAnalytics\Models\AnalyticsEvent;
use App\Features\GameAnalytics\Requests\AnalyticsExportRequest;
use App\Features\GameAnalytics\Requests\AnalyticsFilterRequest;
use App\Features\GameAnalytics\Resources\AnalyticsPayloadResource;
use App\Features\GameAnalytics\Support\AnalyticsFilterFactory;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AnalyticsController extends Controller
{
    public function overview(AnalyticsFilterRequest $request, GetAnalyticsOverview $action): AnalyticsPayloadResource
    {
        $this->authorize('viewDashboard', AnalyticsEvent::class);

        return new AnalyticsPayloadResource($action->execute(AnalyticsFilterFactory::fromRequest($request)));
    }

    public function revenue(AnalyticsFilterRequest $request, GetAnalyticsRevenue $action): AnalyticsPayloadResource
    {
        $this->authorize('viewDashboard', AnalyticsEvent::class);

        return new AnalyticsPayloadResource($action->execute(AnalyticsFilterFactory::fromRequest($request)));
    }

    public function games(AnalyticsFilterRequest $request, GetAnalyticsGames $action): AnalyticsPayloadResource
    {
        $this->authorize('viewDashboard', AnalyticsEvent::class);

        return new AnalyticsPayloadResource($action->execute(AnalyticsFilterFactory::fromRequest($request)));
    }

    public function players(AnalyticsFilterRequest $request, GetAnalyticsPlayers $action): AnalyticsPayloadResource
    {
        $this->authorize('viewDashboard', AnalyticsEvent::class);

        return new AnalyticsPayloadResource($action->execute(AnalyticsFilterFactory::fromRequest($request)));
    }

    public function retention(AnalyticsFilterRequest $request, GetAnalyticsRetention $action): AnalyticsPayloadResource
    {
        $this->authorize('viewDashboard', AnalyticsEvent::class);

        return new AnalyticsPayloadResource($action->execute(AnalyticsFilterFactory::fromRequest($request)));
    }

    public function live(AnalyticsFilterRequest $request, GetAnalyticsLive $action): AnalyticsPayloadResource
    {
        $this->authorize('viewDashboard', AnalyticsEvent::class);

        return new AnalyticsPayloadResource($action->execute(AnalyticsFilterFactory::fromRequest($request)));
    }

    public function agencies(AnalyticsFilterRequest $request, GetAnalyticsAgencies $action): AnalyticsPayloadResource
    {
        $this->authorize('viewDashboard', AnalyticsEvent::class);

        return new AnalyticsPayloadResource($action->execute(AnalyticsFilterFactory::fromRequest($request)));
    }

    public function vip(AnalyticsFilterRequest $request, GetAnalyticsVip $action): AnalyticsPayloadResource
    {
        $this->authorize('viewDashboard', AnalyticsEvent::class);

        return new AnalyticsPayloadResource($action->execute(AnalyticsFilterFactory::fromRequest($request)));
    }

    public function export(AnalyticsExportRequest $request, ExportAnalytics $action): Response|JsonResponse
    {
        $this->authorize('export', AnalyticsEvent::class);

        $filters = new AnalyticsFilterData(
            from: $request->filled('from')
                ? CarbonImmutable::parse((string) $request->input('from'))->startOfDay()
                : CarbonImmutable::now()->subDays(30)->startOfDay(),
            to: $request->filled('to')
                ? CarbonImmutable::parse((string) $request->input('to'))->endOfDay()
                : CarbonImmutable::now()->endOfDay(),
            gameSlug: $request->input('game_slug'),
            countryCode: $request->input('country_code'),
            devicePlatform: $request->input('device_platform'),
            limit: 500,
        );

        $result = $action->execute(new ExportRequestData(
            format: (string) $request->input('format'),
            reportType: (string) $request->input('report_type', 'overview'),
            filters: $filters,
            columns: array_values($request->input('columns', [])),
        ));

        $wantsJson = $request->expectsJson() || $request->boolean('as_json');
        if ($wantsJson) {
            $isBinary = in_array(strtolower((string) $request->input('format')), ['pdf', 'xls', 'excel'], true);

            return response()->json([
                'data' => [
                    'filename' => $result['filename'],
                    'file_name' => $result['filename'],
                    'mime_type' => $result['mime'],
                    'encoding' => $isBinary ? 'base64' : 'utf8',
                    'content' => $isBinary ? base64_encode($result['contents']) : $result['contents'],
                    'download_url' => null,
                ],
            ]);
        }

        return response($result['contents'], 200, [
            'Content-Type' => $result['mime'],
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
        ]);
    }
}
