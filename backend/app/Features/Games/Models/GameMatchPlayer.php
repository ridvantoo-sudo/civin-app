<?php

namespace App\Features\Games\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMatchPlayer extends Model
{
    use HasUuids;

    public const STATUS_JOINED = 'joined';

    public const STATUS_READY = 'ready';

    public const STATUS_PLAYING = 'playing';

    public const STATUS_LEFT = 'left';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    protected $fillable = [
        'match_id',
        'user_id',
        'seat',
        'status',
        'score',
        'state',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'seat' => 'integer',
            'score' => 'integer',
            'state' => 'array',
            'metadata' => 'array',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
