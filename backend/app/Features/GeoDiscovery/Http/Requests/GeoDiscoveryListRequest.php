<?php

namespace App\Features\GeoDiscovery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GeoDiscoveryListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'radius_km' => ['sometimes', 'numeric', 'min:1', 'max:200'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'country' => ['nullable', 'string', 'max:100'],
            'period' => ['sometimes', 'string', 'in:daily,weekly,monthly,DAILY,WEEKLY,MONTHLY'],
            'type' => ['sometimes', 'string', 'in:host,gifter,pk,voice,HOST_DIAMONDS,TOP_GIFTER,PK_WINNER,VOICE_HOST'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }

    public function radiusKm(): float
    {
        return (float) ($this->validated('radius_km') ?? 25);
    }

    public function latitude(): ?float
    {
        $value = $this->validated('latitude') ?? null;

        return $value === null ? null : (float) $value;
    }

    public function longitude(): ?float
    {
        $value = $this->validated('longitude') ?? null;

        return $value === null ? null : (float) $value;
    }

    public function country(): ?string
    {
        $value = $this->validated('country') ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function period(): string
    {
        $period = strtoupper((string) ($this->validated('period') ?? 'daily'));

        return match ($period) {
            'DAILY', 'WEEKLY', 'MONTHLY' => $period,
            default => 'DAILY',
        };
    }

    public function rankingType(): string
    {
        $type = (string) ($this->validated('type') ?? 'host');

        return match (strtolower($type)) {
            'host', 'host_diamonds' => 'HOST_DIAMONDS',
            'gifter', 'top_gifter' => 'TOP_GIFTER',
            'pk', 'pk_winner' => 'PK_WINNER',
            'voice', 'voice_host' => 'VOICE_HOST',
            default => strtoupper($type),
        };
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? 50);
    }
}
