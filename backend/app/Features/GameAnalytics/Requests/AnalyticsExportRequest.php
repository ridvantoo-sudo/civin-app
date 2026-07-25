<?php

namespace App\Features\GameAnalytics\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'report_type' => $this->input('report_type', $this->input('section', 'overview')),
            'game_slug' => $this->input('game_slug', $this->input('game')),
            'country_code' => $this->input('country_code', $this->input('country')),
            'device_platform' => $this->input('device_platform', $this->input('platform')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', Rule::in(['csv', 'xls', 'excel', 'pdf'])],
            'report_type' => ['nullable', Rule::in([
                'overview', 'revenue', 'games', 'players', 'retention', 'live', 'agencies', 'vip',
            ])],
            'section' => ['nullable', Rule::in([
                'overview', 'revenue', 'games', 'players', 'retention', 'live', 'agencies', 'vip',
            ])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'game_slug' => ['nullable', 'string', 'max:64'],
            'game' => ['nullable', 'string', 'max:64'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'country' => ['nullable', 'string', 'max:8'],
            'device_platform' => ['nullable', 'string', 'max:32'],
            'platform' => ['nullable', 'string', 'max:32'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', 'max:64'],
        ];
    }
}
