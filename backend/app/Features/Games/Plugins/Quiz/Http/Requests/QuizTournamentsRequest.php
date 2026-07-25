<?php

namespace App\Features\Games\Plugins\Quiz\Http\Requests;

use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use Illuminate\Foundation\Http\FormRequest;

final class QuizTournamentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewConfig', QuizSession::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
