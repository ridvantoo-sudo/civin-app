<?php

namespace App\Features\Levels\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLevelProgress extends Model
{
    use HasUuids;

    protected $table = 'user_level_progress';

    protected $fillable = [
        'user_id',
        'level',
        'xp_total',
        'xp_into_level',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'xp_total' => 'integer',
            'xp_into_level' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
