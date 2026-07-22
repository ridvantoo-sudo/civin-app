<?php

namespace App\Features\Ranking\Http\Requests;

use App\Features\Ranking\Models\Ranking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListRankingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(Ranking::PERIODS)],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function period(): string
    {
        return (string) ($this->validated('period') ?? Ranking::PERIOD_DAILY);
    }

    public function country(): ?string
    {
        $country = $this->validated('country') ?? null;

        return $country === null || $country === '' ? null : (string) $country;
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? 50);
    }
}
