<?php

namespace App\Features\LiveChat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListLiveMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }
}
