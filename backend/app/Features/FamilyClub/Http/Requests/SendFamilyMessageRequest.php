<?php

namespace App\Features\FamilyClub\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SendFamilyMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
