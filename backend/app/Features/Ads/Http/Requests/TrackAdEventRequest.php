<?php

namespace App\Features\Ads\Http\Requests;

use App\Features\Ads\Models\AdEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TrackAdEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', Rule::in(AdEvent::types())],
            'context_id' => ['nullable', 'string', 'max:64'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
