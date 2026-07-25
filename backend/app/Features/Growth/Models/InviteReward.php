<?php

namespace App\Features\Growth\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteReward extends Model
{
    use HasUuids;

    public const ROLE_INVITER = 'inviter';

    public const ROLE_INVITEE = 'invitee';

    public const STATUS_GRANTED = 'granted';

    protected $fillable = [
        'invite_id',
        'user_id',
        'role',
        'coins',
        'diamonds',
        'status',
        'granted_at',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'diamonds' => 'integer',
            'granted_at' => 'datetime',
        ];
    }

    public function invite(): BelongsTo
    {
        return $this->belongsTo(Invite::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
