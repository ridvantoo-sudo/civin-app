<?php

namespace App\Features\Games\Plugins\Quiz\Http\Requests;

use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use Illuminate\Foundation\Http\FormRequest;

final class SubmitAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = QuizSession::query()->find($this->input('session_id'));
        if ($session === null) {
            return $this->user() !== null;
        }

        return $this->user()?->can('answer', $session) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'session_id' => ['required', 'uuid'],
            'question_id' => ['required', 'uuid'],
            'selected_index' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'selected_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'client_response_ms' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:600000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'is_correct' => ['prohibited'],
            'points_awarded' => ['prohibited'],
            'score' => ['prohibited'],
        ];
    }
}
