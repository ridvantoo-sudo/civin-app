<?php

namespace App\Features\Games\Plugins\LuckyNumber\Http\Requests;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use Illuminate\Foundation\Http\FormRequest;

final class LuckyNumberDrawsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewDraws', LuckyNumberTicket::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
