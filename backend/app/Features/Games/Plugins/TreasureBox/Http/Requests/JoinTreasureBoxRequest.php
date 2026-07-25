<?php

namespace App\Features\Games\Plugins\TreasureBox\Http\Requests;

use App\Features\Games\Models\TreasureBox;
use Illuminate\Foundation\Http\FormRequest;

final class JoinTreasureBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('join', TreasureBox::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'treasure_box_id' => ['required', 'uuid'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'reward_amount' => ['prohibited'],
            'is_jackpot' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
