<?php

namespace App\Features\Ai\Contracts;

interface AiProvider
{
    public function name(): string;

    /**
     * @return array{verdict: string, labels: list<string>, confidence: float}
     */
    public function moderateText(string $text, string $surface): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{verdict: string, labels: list<string>, confidence: float}
     */
    public function moderateVoice(array $payload): array;

    /**
     * @return array{translated_text: string, source_locale: string, target_locale: string}
     */
    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): array;

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{id: string, score: float, reason: string}>
     */
    public function recommend(string $context, int $limit, array $contextPayload = []): array;

    /**
     * @return list<array{start_ms: int, end_ms: int, title: string, score: float}>
     */
    public function suggestHighlights(string $roomId, ?string $replayId = null): array;
}
