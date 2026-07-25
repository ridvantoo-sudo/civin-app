<?php

namespace App\Features\GameAnalytics\Services;

use App\Features\GameAnalytics\DTO\AnalyticsFilterData;
use App\Features\GameAnalytics\Repositories\Contracts\AnalyticsRepository;
use Carbon\CarbonImmutable;

final class SnapshotService
{
    public function __construct(
        private readonly AnalyticsRepository $repository,
        private readonly AnalyticsQueryService $query,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function capture(string $type = 'executive', ?AnalyticsFilterData $filters = null): array
    {
        $filters ??= new AnalyticsFilterData(
            from: CarbonImmutable::now()->subDays(7)->startOfDay(),
            to: CarbonImmutable::now()->endOfDay(),
            limit: 100,
        );

        $payload = match ($type) {
            'revenue' => $this->query->revenue($filters),
            'players' => $this->query->players($filters),
            'retention' => $this->query->retention($filters),
            'live' => $this->query->live($filters),
            default => $this->query->overview($filters),
        };

        $snapshot = $this->repository->storeSnapshot(
            $type,
            CarbonImmutable::now()->format('Y-m-d-H'),
            $payload,
            ['source' => 'snapshot_service'],
        );

        return [
            'id' => $snapshot->id,
            'type' => $snapshot->snapshot_type,
            'captured_at' => $snapshot->captured_at?->toIso8601String(),
            'payload' => $snapshot->payload,
        ];
    }
}
