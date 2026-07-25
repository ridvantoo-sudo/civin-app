<?php

namespace App\Features\LiveShopping\Http\Requests;

use App\Features\LiveShopping\Models\ShopOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ShopOrder::shippingStatuses())],
            'tracking_number' => ['nullable', 'string', 'max:120'],
        ];
    }
}
