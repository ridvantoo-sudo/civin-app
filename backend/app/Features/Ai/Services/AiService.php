<?php

namespace App\Features\Ai\Services;

use App\Features\Ai\Contracts\AiProvider;
use App\Features\Ai\DTOs\ModerateTextData;
use App\Features\Ai\DTOs\ModerateVoiceData;
use App\Features\Ai\DTOs\RecommendData;
use App\Features\Ai\DTOs\SuggestHighlightsData;
use App\Features\Ai\DTOs\TranslateData;
use App\Features\Ai\Models\AiJob;
use App\Features\Ai\Models\AiModerationLog;
use App\Features\Ai\Support\AiJobStatus;
use App\Features\Ai\Support\AiJobType;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class AiService
{
    public function __construct(private AiProvider $provider) {}

    public function moderateText(User $user, ModerateTextData $data): AiJob
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit('ai-moderate:'.$user->getKey(), (int) config('ai.rate_limits.moderate', 60));

        if (trim($data->text) === '') {
            throw ValidationException::withMessages(['text' => 'Text is required.']);
        }

        $job = $this->startJob($user, AiJobType::MODERATE_TEXT, [
            'text' => $data->text,
            'surface' => $data->surface,
            'subject_type' => $data->subjectType,
            'subject_id' => $data->subjectId,
        ]);

        try {
            $result = $this->provider->moderateText($data->text, $data->surface);
            $this->finishJob($job, $result);
            $this->logModeration($job, $user, $data->surface, $data->subjectType, $data->subjectId, $result);
        } catch (\Throwable $e) {
            $this->failJob($job, $e->getMessage());
            throw $e;
        }

        return $job->fresh('moderationLog');
    }

    public function moderateVoice(User $user, ModerateVoiceData $data): AiJob
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit('ai-moderate:'.$user->getKey(), (int) config('ai.rate_limits.moderate', 60));

        $payload = [
            'surface' => $data->surface,
            'audio_url' => $data->audioUrl,
            'duration_ms' => $data->durationMs,
            'room_id' => $data->roomId,
            'subject_type' => $data->subjectType,
            'subject_id' => $data->subjectId,
        ];

        $job = $this->startJob($user, AiJobType::MODERATE_VOICE, $payload);

        try {
            $result = $this->provider->moderateVoice($payload);
            $this->finishJob($job, $result);
            $this->logModeration($job, $user, $data->surface, $data->subjectType, $data->subjectId, $result);
        } catch (\Throwable $e) {
            $this->failJob($job, $e->getMessage());
            throw $e;
        }

        return $job->fresh('moderationLog');
    }

    public function translate(User $user, TranslateData $data): AiJob
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit('ai-translate:'.$user->getKey(), (int) config('ai.rate_limits.translate', 60));

        if (trim($data->text) === '') {
            throw ValidationException::withMessages(['text' => 'Text is required.']);
        }
        if (trim($data->targetLocale) === '') {
            throw ValidationException::withMessages(['target_locale' => 'Target locale is required.']);
        }

        $job = $this->startJob($user, AiJobType::TRANSLATE, [
            'text' => $data->text,
            'target_locale' => $data->targetLocale,
            'source_locale' => $data->sourceLocale,
        ]);

        try {
            $result = $this->provider->translate($data->text, $data->targetLocale, $data->sourceLocale);
            $this->finishJob($job, $result);
        } catch (\Throwable $e) {
            $this->failJob($job, $e->getMessage());
            throw $e;
        }

        return $job->fresh();
    }

    public function recommend(User $user, RecommendData $data): AiJob
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit('ai-recommend:'.$user->getKey(), (int) config('ai.rate_limits.recommend', 30));

        $context = $data->context !== '' ? $data->context : 'discovery';
        $job = $this->startJob($user, AiJobType::RECOMMEND, [
            'context' => $context,
            'limit' => $data->limit,
            'payload' => $data->payload,
        ]);

        try {
            $items = $this->provider->recommend($context, $data->limit, $data->payload ?? []);
            $this->finishJob($job, ['items' => $items, 'context' => $context]);
        } catch (\Throwable $e) {
            $this->failJob($job, $e->getMessage());
            throw $e;
        }

        return $job->fresh();
    }

    public function suggestHighlights(User $user, SuggestHighlightsData $data): AiJob
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit('ai-highlights:'.$user->getKey(), (int) config('ai.rate_limits.highlights', 20));

        $job = $this->startJob($user, AiJobType::HIGHLIGHT_SUGGEST, [
            'room_id' => $data->roomId,
            'replay_id' => $data->replayId,
        ]);

        try {
            $suggestions = $this->provider->suggestHighlights($data->roomId, $data->replayId);
            $this->finishJob($job, [
                'room_id' => $data->roomId,
                'replay_id' => $data->replayId,
                'suggestions' => $suggestions,
            ]);
        } catch (\Throwable $e) {
            $this->failJob($job, $e->getMessage());
            throw $e;
        }

        return $job->fresh();
    }

    public function job(User $user, AiJob $job): AiJob
    {
        $this->ensureEligible($user);
        if ($job->user_id !== null && $job->user_id !== $user->getKey()) {
            throw new AuthorizationException('You cannot view this AI job.');
        }

        return $job->loadMissing('moderationLog');
    }

    private function startJob(User $user, string $type, array $input): AiJob
    {
        return AiJob::query()->create([
            'user_id' => $user->getKey(),
            'type' => $type,
            'status' => AiJobStatus::PROCESSING,
            'provider' => $this->provider->name(),
            'input' => $input,
        ]);
    }

    private function finishJob(AiJob $job, array $result): void
    {
        $job->forceFill([
            'status' => AiJobStatus::COMPLETED,
            'result' => $result,
            'error' => null,
        ])->save();
    }

    private function failJob(AiJob $job, string $message): void
    {
        $job->forceFill([
            'status' => AiJobStatus::FAILED,
            'error' => mb_substr($message, 0, 1000),
        ])->save();
    }

    /**
     * @param  array{verdict: string, labels: list<string>, confidence: float}  $result
     */
    private function logModeration(
        AiJob $job,
        User $user,
        string $surface,
        ?string $subjectType,
        ?string $subjectId,
        array $result,
    ): void {
        AiModerationLog::query()->create([
            'job_id' => $job->getKey(),
            'user_id' => $user->getKey(),
            'surface' => $surface,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'verdict' => $result['verdict'],
            'labels' => $result['labels'],
            'confidence' => $result['confidence'],
            'provider' => $this->provider->name(),
        ]);
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use AI stubs.');
        }
    }

    private function enforceRateLimit(string $key, int $max): void
    {
        if (RateLimiter::tooManyAttempts($key, max(1, $max))) {
            throw ValidationException::withMessages(['request' => 'Too many AI requests.']);
        }
        RateLimiter::hit($key, 60);
    }
}
