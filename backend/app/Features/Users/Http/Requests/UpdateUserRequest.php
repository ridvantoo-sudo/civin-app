<?php

namespace App\Features\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['sometimes', 'nullable', 'email', Rule::unique('users')->ignore($this->user()->id)],
            'username' => ['sometimes', 'string', 'min:3', 'max:40', 'alpha_dash', Rule::unique('users')->ignore($this->user()->id)],
        ];
    }
}
