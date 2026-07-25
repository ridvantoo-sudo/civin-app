<?php

namespace App\Features\Games\Plugins\Quiz\Http\Requests;

use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use Illuminate\Foundation\Http\FormRequest;

final class StartQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = QuizSession::query()->find($this->input('session_id'));
        if ($session === null) {
            return $this->user() !== null;
        }

        return $this->user()?->can('start', $session) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'session_id' => ['required', 'uuid'],
        ];
    }
}
