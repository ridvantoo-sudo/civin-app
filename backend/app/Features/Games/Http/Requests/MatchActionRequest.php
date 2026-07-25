<?php

namespace App\Features\Games\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MatchActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'max:32'],
            'payload' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
