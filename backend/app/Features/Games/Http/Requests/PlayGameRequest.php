<?php

namespace App\Features\Games\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PlayGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'game_slug' => ['required', 'string', 'max:64'],
            'bet_coins' => ['sometimes', 'integer', 'min:0'],
            'input' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
