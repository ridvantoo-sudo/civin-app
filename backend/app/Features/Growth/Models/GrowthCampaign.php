<?php

namespace App\Features\Growth\Models;

use App\Features\Growth\Support\CampaignStatus;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthCampaign extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'channel',
        'status',
        'subject',
        'body',
        'action_url',
        'audience',
        'scheduled_at',
        'started_at',
        'completed_at',
        'targeted_count',
        'sent_count',
        'failed_count',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'targeted_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CampaignDelivery::class, 'campaign_id');
    }

    public function isDispatchable(): bool
    {
        return in_array($this->status, [CampaignStatus::DRAFT, CampaignStatus::SCHEDULED], true);
    }
}
