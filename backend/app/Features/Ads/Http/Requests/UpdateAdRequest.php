<?php

namespace App\Features\Ads\Http\Requests;

use App\Features\Ads\Models\AdCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:2000'],
            'placement' => ['sometimes', 'string', Rule::in(AdCampaign::placements())],
            'media_url' => ['nullable', 'url', 'max:2048'],
            'media_type' => ['sometimes', 'string', Rule::in(AdCampaign::mediaTypes())],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'cta_url' => ['nullable', 'url', 'max:2048'],
            'target_type' => ['sometimes', 'string', Rule::in(AdCampaign::targetTypes())],
            'target_payload' => ['nullable', 'array'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'budget_impressions' => ['nullable', 'integer', 'min:1'],
            'budget_clicks' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
