<?php

namespace App\Features\Kyc\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListKycPagedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }

    public function status(): ?string
    {
        $status = $this->validated('status') ?? null;

        return is_string($status) && $status !== '' ? $status : null;
    }
}
