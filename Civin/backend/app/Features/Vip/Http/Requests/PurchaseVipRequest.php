<?php

namespace App\Features\Vip\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PurchaseVipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'vip_level_id' => ['required', 'uuid', 'exists:vip_levels,id'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
