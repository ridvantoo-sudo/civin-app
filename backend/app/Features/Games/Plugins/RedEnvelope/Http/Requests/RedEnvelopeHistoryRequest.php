<?php

namespace App\Features\Games\Plugins\RedEnvelope\Http\Requests;

use App\Features\Games\Models\RedEnvelope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RedEnvelopeHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewHistory', RedEnvelope::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(['created', 'claimed', 'all'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
