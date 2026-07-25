<?php

namespace App\Features\SocialEngagement\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorshipSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'mentorship_id',
        'logged_by',
        'duration_minutes',
        'notes',
        'mentor_coins',
        'mentee_coins',
        'held_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'mentor_coins' => 'integer',
            'mentee_coins' => 'integer',
            'held_at' => 'datetime',
        ];
    }

    public function mentorship(): BelongsTo
    {
        return $this->belongsTo(Mentorship::class);
    }

    public function logger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
