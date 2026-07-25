<?php

namespace App\Features\Marketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PurchaseMarketItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'market_item_id' => ['required', 'uuid', 'exists:market_items,id'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
