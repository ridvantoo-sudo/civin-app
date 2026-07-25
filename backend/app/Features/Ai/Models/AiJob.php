<?php

namespace App\Features\Ai\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'provider',
        'input',
        'result',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'result' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderationLog(): HasOne
    {
        return $this->hasOne(AiModerationLog::class, 'job_id');
    }
}
