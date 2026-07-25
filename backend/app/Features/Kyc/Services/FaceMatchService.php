<?php

namespace App\Features\Kyc\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Baseline face-match check used until a third-party provider is wired in.
 * Confirms both images exist and are readable, then flags for manual review.
 */
final class FaceMatchService
{
    /**
     * @return array{score: ?float, status: string}
     */
    public function compare(string $documentFrontPath, string $selfiePath): array
    {
        $disk = Storage::disk((string) config('filesystems.secure_media'));

        if (! $disk->exists($documentFrontPath) || ! $disk->exists($selfiePath)) {
            return [
                'score' => 0.0,
                'status' => 'failed',
            ];
        }

        $documentSize = (int) $disk->size($documentFrontPath);
        $selfieSize = (int) $disk->size($selfiePath);

        if ($documentSize < 100 || $selfieSize < 100) {
            return [
                'score' => 0.0,
                'status' => 'failed',
            ];
        }

        // Soft heuristic only — final decision is always manual admin approval.
        $ratio = min($documentSize, $selfieSize) / max($documentSize, $selfieSize);
        $score = round(55 + ($ratio * 35), 2);

        return [
            'score' => $score,
            'status' => 'requires_manual',
        ];
    }
}
