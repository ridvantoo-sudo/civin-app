<?php

namespace App\Features\Ai\Actions;

use App\Features\Ai\DTOs\ModerateTextData;
use App\Features\Ai\DTOs\ModerateVoiceData;
use App\Features\Ai\DTOs\RecommendData;
use App\Features\Ai\DTOs\SuggestHighlightsData;
use App\Features\Ai\DTOs\TranslateData;
use App\Features\Ai\Models\AiJob;
use App\Features\Ai\Services\AiService;
use App\Features\Users\Models\User;

final readonly class ManageAi
{
    public function __construct(private AiService $ai) {}

    public function moderateText(User $user, ModerateTextData $data): AiJob
    {
        return $this->ai->moderateText($user, $data);
    }

    public function moderateVoice(User $user, ModerateVoiceData $data): AiJob
    {
        return $this->ai->moderateVoice($user, $data);
    }

    public function translate(User $user, TranslateData $data): AiJob
    {
        return $this->ai->translate($user, $data);
    }

    public function recommend(User $user, RecommendData $data): AiJob
    {
        return $this->ai->recommend($user, $data);
    }

    public function suggestHighlights(User $user, SuggestHighlightsData $data): AiJob
    {
        return $this->ai->suggestHighlights($user, $data);
    }

    public function job(User $user, AiJob $job): AiJob
    {
        return $this->ai->job($user, $job);
    }
}
