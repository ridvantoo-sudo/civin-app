<?php

namespace App\Features\FamilyClub\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class JoinFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:4', 'max:32'],
        ];
    }
}
