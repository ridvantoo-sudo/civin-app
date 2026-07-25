<?php

namespace App\Features\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SuggestHighlightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'uuid'],
            'replay_id' => ['nullable', 'uuid'],
        ];
    }
}
