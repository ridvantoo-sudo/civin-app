<?php

namespace App\Features\Games\Plugins\RedEnvelope\Http\Requests;

use App\Features\Games\Models\RedEnvelope;
use Illuminate\Foundation\Http\FormRequest;

final class RedEnvelopeConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewConfig', RedEnvelope::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
