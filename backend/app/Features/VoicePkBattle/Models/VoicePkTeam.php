<?php

namespace App\Features\VoicePkBattle\Models;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VoicePkTeam extends Model
{
    use HasUuids;

    protected $fillable = [
        'voice_pk_battle_id', 'room_id', 'host_id', 'accepted', 'accepted_at',
        'team_score', 'gift_score',
    ];

    protected function casts(): array
    {
        return [
            'accepted' => 'boolean',
            'accepted_at' => 'datetime',
            'team_score' => 'integer',
            'gift_score' => 'integer',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(VoicePkBattle::class, 'voice_pk_battle_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(VoiceRoom::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }
}
