<?php

namespace App\Features\Global\Http\Controllers;

use App\Features\Global\Services\CdnService;
use App\Features\Global\Services\GeoComplianceService;
use App\Features\Global\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class GlobalController extends Controller
{
    public function evaluateCompliance(Request $request, GeoComplianceService $compliance): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'max:2'],
            'region_code' => ['nullable', 'string', 'max:16'],
            'feature' => ['sometimes', 'string', 'max:64'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
        ]);

        $result = $compliance->evaluate(
            $request->user(),
            (string) $validated['country_code'],
            $validated['region_code'] ?? null,
            (string) ($validated['feature'] ?? '*'),
            isset($validated['age']) ? (int) $validated['age'] : null,
        );

        return response()->json(['data' => $result]);
    }

    public function timezones(TimezoneService $timezones): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn (string $tz): array => ['id' => $tz],
                $timezones->catalog(),
            ),
        ]);
    }

    public function getTimezone(Request $request, TimezoneService $timezones): JsonResponse
    {
        return response()->json([
            'data' => $timezones->getPreference($request->user()),
        ]);
    }

    public function putTimezone(Request $request, TimezoneService $timezones): JsonResponse
    {
        $validated = $request->validate([
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        return response()->json([
            'data' => $timezones->setPreference($request->user(), (string) $validated['timezone']),
        ]);
    }

    public function convertTimezone(Request $request, TimezoneService $timezones): JsonResponse
    {
        $validated = $request->validate([
            'local' => ['required', 'string', 'max:64'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json([
            'data' => $timezones->convert(
                $request->user(),
                (string) $validated['local'],
                $validated['timezone'] ?? null,
            ),
        ]);
    }

    public function createSchedule(Request $request, TimezoneService $timezones): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'local' => ['required', 'string', 'max:64'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'payload' => ['nullable', 'array'],
        ]);

        $schedule = $timezones->schedule(
            $request->user(),
            (string) $validated['name'],
            (string) $validated['local'],
            $validated['timezone'] ?? null,
            $validated['payload'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'timezone' => $schedule->timezone,
                'local_wall_time' => $schedule->local_wall_time,
                'run_at_utc' => $schedule->run_at_utc?->toIso8601String(),
                'status' => $schedule->status,
                'payload' => $schedule->payload,
            ],
        ], 201);
    }

    public function listSchedules(Request $request, TimezoneService $timezones): JsonResponse
    {
        $rows = $timezones->mySchedules($request->user())->map(static fn ($s): array => [
            'id' => $s->id,
            'name' => $s->name,
            'timezone' => $s->timezone,
            'local_wall_time' => $s->local_wall_time,
            'run_at_utc' => $s->run_at_utc?->toIso8601String(),
            'status' => $s->status,
            'payload' => $s->payload,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function cdnConfig(Request $request, CdnService $cdn): JsonResponse
    {
        $country = $request->query('country_code');

        return response()->json([
            'data' => $cdn->config(is_string($country) ? $country : null),
        ]);
    }

    public function cdnResolve(Request $request, CdnService $cdn): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:2048'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'image_optimize' => ['sometimes', 'boolean'],
        ]);

        try {
            $resolved = $cdn->resolve(
                (string) $validated['path'],
                $validated['country_code'] ?? null,
                (bool) ($validated['image_optimize'] ?? false),
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json(['data' => $resolved]);
    }
}
