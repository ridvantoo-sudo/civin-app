<?php

namespace App\Features\Games\Plugins\Quiz\Http\Resources;

use App\Features\Games\Plugins\Quiz\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuizQuestion */
final class QuizQuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Public payload — never expose correct_index or option correctness.
        return [
            'id' => $this->id,
            'question' => $this->prompt ?: $this->question,
            'type' => $this->type ?? 'multiple_choice',
            'language' => $this->language ?? 'en',
            'difficulty' => $this->difficulty,
            'category_id' => $this->category_id,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'options' => $this->options ?? [],
            'translations' => $this->translations,
        ];
    }
}
