<?php

namespace App\Features\Vip\Models;

use App\Features\Users\Models\User;
use Database\Factories\VipTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VipTransaction extends Model
{
    /** @use HasFactory<VipTransactionFactory> */
    use HasFactory, HasUuids;

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_UPGRADE = 'upgrade';

    public const WALLET_TYPE_VIP_PURCHASE = 'VIP_PURCHASE';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'vip_level_id',
        'user_vip_id',
        'type',
        'coins',
        'from_level',
        'to_level',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'from_level' => 'integer',
            'to_level' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(VipLevel::class, 'vip_level_id');
    }

    public function userVip(): BelongsTo
    {
        return $this->belongsTo(UserVip::class, 'user_vip_id');
    }
}
