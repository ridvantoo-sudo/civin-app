<?php

namespace App\Features\Security\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SecurityHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function limit(): int
    {
        return (int) ($this->validated()['limit'] ?? 50);
    }
}
