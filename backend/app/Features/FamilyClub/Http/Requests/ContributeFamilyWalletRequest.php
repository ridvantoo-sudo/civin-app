<?php

namespace App\Features\FamilyClub\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ContributeFamilyWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'currency' => ['nullable', 'string', Rule::in(['coins', 'diamonds'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
