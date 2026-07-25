<?php

namespace App\Features\Ads\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ServeAdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'context_id' => ['nullable', 'string', 'max:64'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
