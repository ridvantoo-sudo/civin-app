<?php

namespace App\Features\Ranking\Models;

use App\Features\Users\Models\User;
use Database\Factories\RankingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ranking extends Model
{
    /** @use HasFactory<RankingFactory> */
    use HasFactory, HasUuids;

    public const TYPE_HOST_DIAMONDS = 'HOST_DIAMONDS';

    public const TYPE_TOP_GIFTER = 'TOP_GIFTER';

    public const TYPE_PK_WINNER = 'PK_WINNER';

    public const TYPE_VOICE_HOST = 'VOICE_HOST';

    public const TYPE_POPULAR_USER = 'POPULAR_USER';

    public const TYPES = [
        self::TYPE_HOST_DIAMONDS,
        self::TYPE_TOP_GIFTER,
        self::TYPE_PK_WINNER,
        self::TYPE_VOICE_HOST,
        self::TYPE_POPULAR_USER,
    ];

    public const PERIOD_DAILY = 'DAILY';

    public const PERIOD_WEEKLY = 'WEEKLY';

    public const PERIOD_MONTHLY = 'MONTHLY';

    public const PERIOD_ALL_TIME = 'ALL_TIME';

    public const PERIODS = [
        self::PERIOD_DAILY,
        self::PERIOD_WEEKLY,
        self::PERIOD_MONTHLY,
        self::PERIOD_ALL_TIME,
    ];

    public $timestamps = false;

    protected $fillable = [
        'type',
        'period',
        'user_id',
        'score',
        'rank',
        'date',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'rank' => 'integer',
            'date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
