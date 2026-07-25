<?php

namespace App\Features\Growth\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralAttribution extends Model
{
    use HasUuids;

    public const STATUS_REWARDED = 'rewarded';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'referrer_id',
        'invitee_id',
        'referral_code_id',
        'status',
        'referrer_coins',
        'invitee_coins',
        'referrer_diamonds',
        'invitee_diamonds',
        'rewarded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'referrer_coins' => 'integer',
            'invitee_coins' => 'integer',
            'referrer_diamonds' => 'integer',
            'invitee_diamonds' => 'integer',
            'rewarded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }
}
