<?php

namespace App\Features\UserStatus\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserStatus extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'user_status';

    protected $fillable = ['user_id', 'is_online', 'is_live', 'last_seen_at', 'live_started_at'];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'is_live' => 'boolean',
            'last_seen_at' => 'datetime',
            'live_started_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
