<?php

namespace App\Features\Discovery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DiscoveryListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'query' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:users,live,voice,all'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }

    public function country(): ?string
    {
        $value = $this->validated('country') ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function categoryId(): ?int
    {
        $value = $this->validated('category_id') ?? null;

        return $value === null ? null : (int) $value;
    }

    public function queryTerm(): ?string
    {
        $value = $this->validated('query') ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function type(): string
    {
        return (string) ($this->validated('type') ?? 'all');
    }
}
