<?php

namespace App\Features\Global\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimezoneSchedule extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'run_at_utc',
        'timezone',
        'local_wall_time',
        'status',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'run_at_utc' => 'datetime',
            'processed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
