<?php

namespace App\Features\Svip\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PurchaseSvipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'svip_level_id' => ['required', 'uuid', 'exists:svip_levels,id'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
