<?php

namespace App\Features\Games\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TrackAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'max:64'],
            'game_slug' => ['sometimes', 'nullable', 'string', 'max:64'],
            'payload' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
