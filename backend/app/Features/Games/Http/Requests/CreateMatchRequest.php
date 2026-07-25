<?php

namespace App\Features\Games\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateMatchRequest extends FormRequest
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
            'stake_coins' => ['sometimes', 'integer', 'min:0'],
            'max_players' => ['sometimes', 'integer', 'min:2', 'max:4'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
