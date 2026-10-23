<?php

namespace App\Features\Ranking\Models;

use Database\Factories\RankingSnapshotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RankingSnapshot extends Model
{
    /** @use HasFactory<RankingSnapshotFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'type',
        'period',
        'data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
