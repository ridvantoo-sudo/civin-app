<?php

namespace App\Features\UserSearch\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:100', 'required_without_all:country,is_online'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_online' => ['nullable', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
