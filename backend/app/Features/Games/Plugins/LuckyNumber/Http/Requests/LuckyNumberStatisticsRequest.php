<?php

namespace App\Features\Games\Plugins\LuckyNumber\Http\Requests;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use Illuminate\Foundation\Http\FormRequest;

final class LuckyNumberStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewStatistics', LuckyNumberTicket::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
