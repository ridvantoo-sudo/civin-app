<?php

namespace App\Features\Ai\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModerationLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'job_id',
        'user_id',
        'surface',
        'subject_type',
        'subject_id',
        'verdict',
        'labels',
        'confidence',
        'provider',
    ];

    protected function casts(): array
    {
        return [
            'labels' => 'array',
            'confidence' => 'float',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AiJob::class, 'job_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
