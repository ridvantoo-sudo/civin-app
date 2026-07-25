<?php

namespace App\Features\Kyc\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycAuditLog extends Model
{
    use HasUuids;

    public const ACTION_SUBMITTED = 'submitted';

    public const ACTION_RESUBMITTED = 'resubmitted';

    public const ACTION_UNDER_REVIEW = 'under_review';

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_FACE_MATCHED = 'face_matched';

    public const ACTION_FACE_FAILED = 'face_failed';

    public const ACTION_WITHDRAWAL_VERIFIED = 'withdrawal_verified';

    public $timestamps = false;

    protected $fillable = [
        'kyc_submission_id',
        'actor_id',
        'action',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(KycSubmission::class, 'kyc_submission_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
