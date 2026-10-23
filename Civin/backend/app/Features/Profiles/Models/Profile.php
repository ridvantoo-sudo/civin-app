<?php

namespace App\Features\Profiles\Models;

use App\Features\Countries\Models\Country;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profile extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'country_id',
        'display_name',
        'bio',
        'avatar_url',
        'cover_image_url',
        'birth_date',
        'gender',
        'level',
        'is_vip',
        'is_private',
        'followers_count',
        'following_count',
        'likes_count',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'level' => 'integer',
            'is_vip' => 'boolean',
            'is_private' => 'boolean',
            'followers_count' => 'integer',
            'following_count' => 'integer',
            'likes_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
