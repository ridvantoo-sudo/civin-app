<?php

namespace App\Features\LiveShopping\Http\Requests;

use App\Features\LiveShopping\Models\ShopOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::in([ShopOrder::METHOD_WALLET, ShopOrder::METHOD_EXTERNAL])],
            'room_id' => ['nullable', 'uuid'],
            'shipping_name' => ['nullable', 'string', 'max:120'],
            'shipping_phone' => ['nullable', 'string', 'max:40'],
            'shipping_address_line' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['nullable', 'string', 'max:120'],
            'shipping_country' => ['nullable', 'string', 'max:120'],
            'shipping_postal_code' => ['nullable', 'string', 'max:32'],
            'payment_provider' => ['nullable', 'string', 'max:64'],
            'payment_transaction_id' => ['nullable', 'string', 'max:191'],
            'clear_cart' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'uuid'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:99'],
            'items.*.live_pin_id' => ['nullable', 'uuid'],
            'coupon_code' => ['nullable', 'string', 'max:32'],
        ];
    }
}
