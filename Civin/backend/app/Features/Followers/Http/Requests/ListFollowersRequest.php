<?php

namespace App\Features\Followers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListFollowersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 20);
    }
}
