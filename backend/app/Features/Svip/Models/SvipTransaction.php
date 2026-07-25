<?php

namespace App\Features\Svip\Models;

use App\Features\Users\Models\User;
use Database\Factories\SvipTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SvipTransaction extends Model
{
    /** @use HasFactory<SvipTransactionFactory> */
    use HasFactory, HasUuids;

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_UPGRADE = 'upgrade';

    public const WALLET_TYPE_SVIP_PURCHASE = 'SVIP_PURCHASE';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'svip_level_id',
        'user_svip_id',
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
        return $this->belongsTo(SvipLevel::class, 'svip_level_id');
    }

    public function userSvip(): BelongsTo
    {
        return $this->belongsTo(UserSvip::class, 'user_svip_id');
    }
}
