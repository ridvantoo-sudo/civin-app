<?php

namespace App\Features\MultiPkBattle\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MultiPkParticipant extends Model
{
    use HasUuids;

    protected $fillable = [
        'multi_pk_battle_id', 'room_id', 'user_id', 'team', 'accepted',
        'active', 'score', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'team' => 'integer',
            'accepted' => 'boolean',
            'active' => 'boolean',
            'score' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(MultiPkBattle::class, 'multi_pk_battle_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
