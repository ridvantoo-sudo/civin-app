<?php

namespace App\Features\Games\Plugins\Quiz\Http\Requests;

use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use Illuminate\Foundation\Http\FormRequest;

final class JoinQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('join', QuizSession::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'session_id' => ['required', 'uuid'],
            'team_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'team_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
