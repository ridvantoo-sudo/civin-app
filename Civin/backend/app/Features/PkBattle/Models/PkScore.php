<?php

namespace App\Features\PkBattle\Models;

use App\Features\Users\Models\User;
use Database\Factories\PkScoreFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkScore extends Model
{
    /** @use HasFactory<PkScoreFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = 'updated_at';

    public const CREATED_AT = null;

    protected $fillable = [
        'pk_battle_id',
        'user_id',
        'score',
        'gift_coins',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'gift_coins' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(PkBattle::class, 'pk_battle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
