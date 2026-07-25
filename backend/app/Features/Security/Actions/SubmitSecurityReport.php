<?php

namespace App\Features\Security\Actions;

use App\Features\Security\Models\SecurityReview;
use App\Features\Security\Services\SecurityAuditLogger;
use App\Features\Security\Services\SecurityCollector;
use Carbon\CarbonImmutable;

/**
 * Player-submitted abuse report. It never enforces anything on its own — it
 * opens a manual review case and feeds the engine one more signal.
 */
final readonly class SubmitSecurityReport
{
    public function __construct(
        private SecurityCollector $collector,
        private SecurityAuditLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(string $reporterId, array $input): array
    {
        $reportedUserId = isset($input['reported_user_id']) ? (string) $input['reported_user_id'] : null;

        $review = SecurityReview::query()->create([
            'user_id' => $reportedUserId,
            'queue' => 'user_report',
            'reason' => mb_substr((string) ($input['reason'] ?? 'user_report'), 0, 191),
            'status' => SecurityReview::STATUS_PENDING,
            'priority' => 'normal',
            'risk_score' => 0.0,
            'context' => [
                'reporter_id' => $reporterId,
                'category' => $input['category'] ?? 'other',
                'surface' => $input['surface'] ?? null,
                'subject_id' => $input['subject_id'] ?? null,
                'description' => isset($input['description'])
                    ? mb_substr((string) $input['description'], 0, 2000)
                    : null,
            ],
            'due_at' => CarbonImmutable::now()->addDay(),
        ]);

        $this->collector->collectFromDomainEvent('security.client.report', [[
            'user_id' => $reportedUserId ?? $reporterId,
            'reporter_id' => $reporterId,
            'category' => $input['category'] ?? 'other',
            'surface' => $input['surface'] ?? null,
            'subject_id' => $input['subject_id'] ?? null,
        ]]);

        $this->logger->api('report.submitted', 'Player abuse report submitted', $reporterId, [
            'review_id' => $review->id,
            'reported_user_id' => $reportedUserId,
        ]);

        return [
            'review_id' => $review->id,
            'status' => $review->status,
            'submitted_at' => $review->created_at?->toISOString(),
        ];
    }
}
