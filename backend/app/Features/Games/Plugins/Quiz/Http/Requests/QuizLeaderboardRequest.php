<?php

namespace App\Features\Games\Plugins\Quiz\Http\Requests;

use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QuizLeaderboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewConfig', QuizSession::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(['daily', 'weekly', 'monthly', 'all_time', 'all', 'tournament'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'room_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
