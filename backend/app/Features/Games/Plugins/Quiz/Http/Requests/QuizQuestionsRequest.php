<?php

namespace App\Features\Games\Plugins\Quiz\Http\Requests;

use App\Features\Games\Plugins\Quiz\Models\QuizConfig;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QuizQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewConfig', QuizSession::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'difficulty' => ['sometimes', 'nullable', 'string', Rule::in(QuizConfig::DIFFICULTIES)],
            'language' => ['sometimes', 'nullable', 'string', 'max:16'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
