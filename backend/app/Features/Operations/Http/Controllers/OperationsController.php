<?php

namespace App\Features\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class OperationsController extends Controller
{
    public function readiness(Request $request): JsonResponse
    {
        $this->authorizeToken($request, config('operations.readiness_token'));

        $checks = [];

        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (\Throwable) {
            $checks['database'] = false;
        }

        try {
            $key = 'ops:readiness:'.bin2hex(random_bytes(6));
            Cache::put($key, true, 10);
            $checks['cache'] = Cache::get($key) === true;
            Cache::forget($key);
        } catch (\Throwable) {
            $checks['cache'] = false;
        }

        $ready = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
        ], $ready ? 200 : 503);
    }

    public function metrics(Request $request): Response
    {
        $this->authorizeToken($request, config('operations.metrics_token'), required: true);

        $databaseUp = 0;
        $failedJobs = 0;

        try {
            DB::select('select 1');
            $databaseUp = 1;
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            // A failed scrape remains useful: the database gauge reports zero.
        }

        $metrics = [
            '# HELP civin_up Whether the Laravel process is serving metrics.',
            '# TYPE civin_up gauge',
            'civin_up 1',
            '# HELP civin_database_up Whether the application database is reachable.',
            '# TYPE civin_database_up gauge',
            "civin_database_up {$databaseUp}",
            '# HELP civin_queue_failed_jobs Number of records in the failed jobs table.',
            '# TYPE civin_queue_failed_jobs gauge',
            "civin_queue_failed_jobs {$failedJobs}",
            '# HELP civin_php_memory_bytes Current PHP process memory usage.',
            '# TYPE civin_php_memory_bytes gauge',
            'civin_php_memory_bytes '.memory_get_usage(true),
        ];

        return response(implode("\n", $metrics)."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function authorizeToken(
        Request $request,
        mixed $configuredToken,
        bool $required = false,
    ): void {
        $token = is_string($configuredToken) ? $configuredToken : '';

        if ($token === '' && ! $required) {
            return;
        }

        abort_unless(
            $token !== '' && hash_equals($token, (string) $request->bearerToken()),
            403,
        );
    }
}
