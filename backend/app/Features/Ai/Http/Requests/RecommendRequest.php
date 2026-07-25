<?php

namespace App\Features\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecommendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'context' => ['sometimes', 'string', 'in:discovery,feed,hosts,gifts'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
