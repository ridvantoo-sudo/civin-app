<?php

namespace App\Features\Levels\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XpTransaction extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const SOURCE_LIVE = 'live';

    public const SOURCE_WATCHING = 'watching';

    public const SOURCE_GIFT_SEND = 'gift_send';

    public const SOURCE_GIFT_RECEIVE = 'gift_receive';

    public const SOURCE_LOGIN = 'login';

    public const SOURCE_DAILY_TASK = 'daily_task';

    public const SOURCE_BADGE = 'badge';

    public const SOURCE_CHALLENGE = 'challenge';

    public const SOURCES = [
        self::SOURCE_LIVE,
        self::SOURCE_WATCHING,
        self::SOURCE_GIFT_SEND,
        self::SOURCE_GIFT_RECEIVE,
        self::SOURCE_LOGIN,
        self::SOURCE_DAILY_TASK,
        self::SOURCE_BADGE,
        self::SOURCE_CHALLENGE,
    ];

    protected $fillable = [
        'user_id',
        'source',
        'amount',
        'level_before',
        'level_after',
        'xp_total_after',
        'idempotency_key',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'level_before' => 'integer',
            'level_after' => 'integer',
            'xp_total_after' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
