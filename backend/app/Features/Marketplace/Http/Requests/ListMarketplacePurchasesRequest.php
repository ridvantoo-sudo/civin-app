<?php

namespace App\Features\Marketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListMarketplacePurchasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
