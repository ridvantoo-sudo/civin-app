<?php

namespace App\Features\Growth\Http\Requests;

use App\Features\Growth\Support\CampaignChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'channel' => ['required', 'string', Rule::in(CampaignChannel::all())],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'action_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'audience' => ['sometimes', 'nullable', 'array'],
            'audience.type' => ['sometimes', 'string', Rule::in(['all', 'country', 'user_ids'])],
            'audience.country_id' => ['required_if:audience.type,country', 'nullable', 'uuid'],
            'audience.user_ids' => ['required_if:audience.type,user_ids', 'nullable', 'array'],
            'audience.user_ids.*' => ['uuid'],
            'scheduled_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
