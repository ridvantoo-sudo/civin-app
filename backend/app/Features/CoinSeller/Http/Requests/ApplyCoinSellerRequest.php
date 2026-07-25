<?php

namespace App\Features\CoinSeller\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApplyCoinSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'min:2', 'max:150'],
            'contact_info' => ['nullable', 'string', 'max:255'],
            'application_message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
