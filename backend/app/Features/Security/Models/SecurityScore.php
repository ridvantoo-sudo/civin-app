<?php

namespace App\Features\Security\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user score sheet. `risk`, `fraud` and `bot` are higher-is-worse;
 * `trust` and `security` are higher-is-better.
 */
class SecurityScore extends Model
{
    use HasUuids;

    public const BAND_LOW = 'low';

    public const BAND_ELEVATED = 'elevated';

    public const BAND_HIGH = 'high';

    public const BAND_CRITICAL = 'critical';

    protected $table = 'security_scores';

    protected $fillable = [
        'user_id',
        'security_score',
        'trust_score',
        'risk_score',
        'fraud_score',
        'bot_score',
        'signal_count',
        'alert_count',
        'clean_streak_days',
        'band',
        'breakdown',
        'last_signal_at',
        'last_decayed_at',
    ];

    protected function casts(): array
    {
        return [
            'security_score' => 'float',
            'trust_score' => 'float',
            'risk_score' => 'float',
            'fraud_score' => 'float',
            'bot_score' => 'float',
            'signal_count' => 'integer',
            'alert_count' => 'integer',
            'clean_streak_days' => 'integer',
            'breakdown' => 'array',
            'last_signal_at' => 'datetime',
            'last_decayed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function bandFor(float $riskScore): string
    {
        return match (true) {
            $riskScore >= 90 => self::BAND_CRITICAL,
            $riskScore >= 70 => self::BAND_HIGH,
            $riskScore >= 40 => self::BAND_ELEVATED,
            default => self::BAND_LOW,
        };
    }
}
