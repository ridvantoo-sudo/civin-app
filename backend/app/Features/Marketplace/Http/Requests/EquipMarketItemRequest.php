<?php

namespace App\Features\Marketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EquipMarketItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_market_item_id' => ['required', 'uuid', 'exists:user_market_items,id'],
        ];
    }
}
