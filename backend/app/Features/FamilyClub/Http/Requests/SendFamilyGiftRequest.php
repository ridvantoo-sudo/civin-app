<?php

namespace App\Features\FamilyClub\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SendFamilyGiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'gift_id' => ['required', 'uuid', 'exists:gifts,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}
