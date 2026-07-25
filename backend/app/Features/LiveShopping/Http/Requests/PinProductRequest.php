<?php

namespace App\Features\LiveShopping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PinProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productRequired = $this->isMethod('POST');

        return [
            'product_id' => [$productRequired ? 'required' : 'sometimes', 'uuid'],
            'is_pinned' => ['sometimes', 'boolean'],
            'flash_deal_price' => ['nullable', 'integer', 'min:1'],
            'flash_deal_ends_at' => ['nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
