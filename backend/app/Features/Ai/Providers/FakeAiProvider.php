<?php

namespace App\Features\Ai\Providers;

use App\Features\Ai\Contracts\AiProvider;
use App\Features\Ai\Support\AiModerationVerdict;

/**
 * Deterministic local stub — no external LLM calls.
 * Swap via config('ai.driver') when a real provider is wired.
 */
final class FakeAiProvider implements AiProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function moderateText(string $text, string $surface): array
    {
        $lower = mb_strtolower($text);
        $blockWords = config('ai.fake.block_words', ['terror', 'bomb']);
        $flagWords = config('ai.fake.flag_words', ['spam', 'scam']);

        foreach ($blockWords as $word) {
            if ($word !== '' && str_contains($lower, (string) $word)) {
                return [
                    'verdict' => AiModerationVerdict::BLOCK,
                    'labels' => ['severe', (string) $word],
                    'confidence' => 0.97,
                ];
            }
        }

        foreach ($flagWords as $word) {
            if ($word !== '' && str_contains($lower, (string) $word)) {
                return [
                    'verdict' => AiModerationVerdict::FLAG,
                    'labels' => ['suspicious', (string) $word],
                    'confidence' => 0.82,
                ];
            }
        }

        return [
            'verdict' => AiModerationVerdict::ALLOW,
            'labels' => ['clean'],
            'confidence' => 0.91,
        ];
    }

    public function moderateVoice(array $payload): array
    {
        $duration = (int) ($payload['duration_ms'] ?? 0);
        if ($duration > 0 && $duration < 400) {
            return [
                'verdict' => AiModerationVerdict::FLAG,
                'labels' => ['too_short', 'possible_noise'],
                'confidence' => 0.65,
            ];
        }

        return [
            'verdict' => AiModerationVerdict::ALLOW,
            'labels' => ['voice_ok'],
            'confidence' => 0.88,
        ];
    }

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): array
    {
        $prefix = (string) config('ai.fake.translate_prefix', '[translated]');
        $source = $sourceLocale ?: 'auto';

        return [
            'translated_text' => trim($prefix.' '.$text),
            'source_locale' => $source,
            'target_locale' => $targetLocale,
        ];
    }

    public function recommend(string $context, int $limit, array $contextPayload = []): array
    {
        $seed = (int) config('ai.fake.recommend_seed', 42);
        $limit = max(1, min($limit, 50));
        $items = [];

        for ($i = 1; $i <= $limit; $i++) {
            $score = round((($seed * $i) % 1000) / 1000, 3);
            $items[] = [
                'id' => sprintf('%s-stub-%02d', $context, $i),
                'score' => $score,
                'reason' => "fake_{$context}_affinity",
            ];
        }

        usort($items, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $items;
    }

    public function suggestHighlights(string $roomId, ?string $replayId = null): array
    {
        $hash = abs(crc32($roomId.($replayId ?? '')));

        return [
            [
                'start_ms' => 15_000 + ($hash % 5_000),
                'end_ms' => 35_000 + ($hash % 5_000),
                'title' => 'Peak engagement',
                'score' => 0.91,
            ],
            [
                'start_ms' => 90_000 + ($hash % 8_000),
                'end_ms' => 120_000 + ($hash % 8_000),
                'title' => 'Gift spike',
                'score' => 0.84,
            ],
            [
                'start_ms' => 180_000 + ($hash % 10_000),
                'end_ms' => 210_000 + ($hash % 10_000),
                'title' => 'Chat burst',
                'score' => 0.76,
            ],
        ];
    }
}
