<?php

namespace App\Features\PkBattle\Models;

use App\Features\Users\Models\User;
use Database\Factories\PkRewardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkReward extends Model
{
    /** @use HasFactory<PkRewardFactory> */
    use HasFactory, HasUuids;

    public const TYPE_DIAMONDS = 'DIAMONDS';

    public $timestamps = false;

    protected $fillable = [
        'pk_battle_id',
        'winner_id',
        'reward_type',
        'amount',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(PkBattle::class, 'pk_battle_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}
