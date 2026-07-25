<?php

namespace App\Features\Marketplace\Http\Requests;

use App\Features\Marketplace\Models\MarketItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListMarketItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'nullable', 'string', Rule::in(MarketItem::types())],
        ];
    }
}
