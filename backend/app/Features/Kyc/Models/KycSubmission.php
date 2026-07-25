<?php

namespace App\Features\Kyc\Models;

use App\Features\Users\Models\User;
use Database\Factories\KycSubmissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class KycSubmission extends Model
{
    /** @use HasFactory<KycSubmissionFactory> */
    use HasFactory, HasUuids;

    public const DOCUMENT_NATIONAL_ID = 'national_id';

    public const DOCUMENT_PASSPORT = 'passport';

    public const STATUS_PENDING = 'pending';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const FACE_MATCH_PENDING = 'pending';

    public const FACE_MATCH_MATCHED = 'matched';

    public const FACE_MATCH_FAILED = 'failed';

    public const FACE_MATCH_REQUIRES_MANUAL = 'requires_manual';

    protected $fillable = [
        'user_id',
        'document_type',
        'document_number',
        'full_legal_name',
        'nationality',
        'date_of_birth',
        'document_front_path',
        'document_back_path',
        'selfie_path',
        'face_match_score',
        'face_match_status',
        'status',
        'withdrawal_verified',
        'reviewed_by',
        'admin_notes',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'face_match_score' => 'decimal:2',
            'withdrawal_verified' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(KycAuditLog::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_UNDER_REVIEW], true);
    }

    public function isOwnedBy(User|string $user): bool
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return $this->user_id === $userId;
    }

    public function allowsWithdrawal(): bool
    {
        return $this->isApproved() && $this->withdrawal_verified;
    }

    public function documentFrontUrl(): ?string
    {
        return $this->publicUrl($this->document_front_path);
    }

    public function documentBackUrl(): ?string
    {
        return $this->publicUrl($this->document_back_path);
    }

    public function selfieUrl(): ?string
    {
        return $this->publicUrl($this->selfie_path);
    }

    /** @return list<string> */
    public static function documentTypes(): array
    {
        return [
            self::DOCUMENT_NATIONAL_ID,
            self::DOCUMENT_PASSPORT,
        ];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }

    /** @return list<string> */
    public static function faceMatchStatuses(): array
    {
        return [
            self::FACE_MATCH_PENDING,
            self::FACE_MATCH_MATCHED,
            self::FACE_MATCH_FAILED,
            self::FACE_MATCH_REQUIRES_MANUAL,
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $diskName = (string) config('filesystems.secure_media');
        $disk = Storage::disk($diskName);

        return config("filesystems.disks.{$diskName}.driver") === 's3'
            ? $disk->temporaryUrl($path, now()->addMinutes(10))
            : $disk->url($path);
    }
}
