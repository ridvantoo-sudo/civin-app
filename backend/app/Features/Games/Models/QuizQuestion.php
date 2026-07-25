<?php

namespace App\Features\Games\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'question',
        'options',
        'correct_index',
        'difficulty',
        'reward_coins',
        'bet_coins',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_index' => 'integer',
            'reward_coins' => 'integer',
            'bet_coins' => 'integer',
        ];
    }
}
