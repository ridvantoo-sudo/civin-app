<?php

namespace App\Features\Security\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail for engine decisions and administrative actions.
 */
class SecurityLog extends Model
{
    use HasUuids;

    public const CHANNEL_ENGINE = 'engine';

    public const CHANNEL_ADMIN = 'admin';

    public const CHANNEL_API = 'api';

    public const CHANNEL_RETENTION = 'retention';

    protected $table = 'security_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'actor_id',
        'channel',
        'level',
        'action',
        'message',
        'subject_type',
        'subject_id',
        'ip_hash',
        'context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
