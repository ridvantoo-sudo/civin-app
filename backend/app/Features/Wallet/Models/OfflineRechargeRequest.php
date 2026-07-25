<?php

namespace App\Features\Wallet\Models;

use App\Features\Users\Models\User;
use Database\Factories\OfflineRechargeRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class OfflineRechargeRequest extends Model
{
    /** @use HasFactory<OfflineRechargeRequestFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'coins',
        'amount',
        'currency',
        'reference_number',
        'receipt_path',
        'status',
        'approved_by',
        'admin_notes',
        'reviewed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'amount' => 'integer',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(OfflineRechargeAuditLog::class);
    }

    public function receiptUrl(): ?string
    {
        if ($this->receipt_path === null || $this->receipt_path === '') {
            return null;
        }

        $diskName = (string) config('filesystems.secure_media');
        $disk = Storage::disk($diskName);

        return config("filesystems.disks.{$diskName}.driver") === 's3'
            ? $disk->temporaryUrl($this->receipt_path, now()->addMinutes(10))
            : $disk->url($this->receipt_path);
    }
}
