<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single hidden move. `move` is never exposed over the API or broadcast
 * until the owning round has been resolved and revealed.
 */
class RpsMove extends Model
{
    use HasUuids;

    protected $table = 'rps_moves';

    protected $hidden = ['move', 'server_seed'];

    protected $fillable = [
        'match_id', 'round_id', 'user_id', 'idempotency_key', 'role', 'round_index',
        'move', 'move_hash', 'server_seed', 'is_auto', 'timed_out',
        'client_response_ms', 'response_ms', 'audit', 'metadata',
        'submitted_at', 'revealed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_auto' => 'boolean',
            'timed_out' => 'boolean',
            'audit' => 'array',
            'metadata' => 'array',
            'submitted_at' => 'datetime',
            'revealed_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(RpsMatch::class, 'match_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(RpsRound::class, 'round_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isRevealed(): bool
    {
        return $this->revealed_at !== null;
    }
}
