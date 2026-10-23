<?php

namespace App\Features\Agency\Models;

use App\Features\Users\Models\User;
use Database\Factories\AgencyCommissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AgencyCommission extends Model
{
    /** @use HasFactory<AgencyCommissionFactory> */
    use HasFactory, HasUuids;

    public const CURRENCY_DIAMONDS = 'diamonds';

    public const WALLET_TYPE_CREDIT = 'AGENCY_COMMISSION';

    public const WALLET_TYPE_DEBIT = 'AGENCY_COMMISSION_DEBIT';

    public $timestamps = false;

    protected $fillable = [
        'agency_id',
        'host_id',
        'agency_member_id',
        'source_type',
        'source_id',
        'gross_amount',
        'commission_rate',
        'commission_amount',
        'host_net_amount',
        'currency',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'integer',
            'host_net_amount' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(AgencyMember::class, 'agency_member_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
