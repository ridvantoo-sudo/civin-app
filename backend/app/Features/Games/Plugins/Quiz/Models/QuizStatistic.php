<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QuizStatistic extends Model
{
    use HasUuids;

    protected $table = 'quiz_statistics';

    protected $fillable = [
        'user_id', 'config_id', 'games_played', 'questions_answered',
        'correct_answers', 'wrong_answers', 'accuracy_percent',
        'average_response_ms', 'highest_score', 'total_score', 'wins',
        'win_rate', 'coins_won', 'diamonds_won', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'accuracy_percent' => 'float',
            'average_response_ms' => 'float',
            'win_rate' => 'float',
            'metadata' => 'array',
        ];
    }
}
