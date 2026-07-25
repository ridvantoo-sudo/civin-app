<?php

namespace App\Features\Games\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LeaderboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'game_slug' => ['sometimes', 'nullable', 'string', 'max:64'],
            'period' => ['sometimes', 'string', Rule::in(['daily', 'weekly', 'all'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
