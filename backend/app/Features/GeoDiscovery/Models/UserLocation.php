<?php

namespace App\Features\GeoDiscovery\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLocation extends Model
{
    use HasUuids;

    public const VISIBILITY_EVERYONE = 'everyone';

    public const VISIBILITY_FOLLOWERS = 'followers';

    public const VISIBILITY_NOBODY = 'nobody';

    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'geohash',
        'geohash_precision',
        'share_location',
        'show_distance',
        'nearby_visibility',
        'located_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'geohash_precision' => 'integer',
            'share_location' => 'boolean',
            'show_distance' => 'boolean',
            'located_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDiscoverable(): bool
    {
        return $this->share_location
            && $this->nearby_visibility !== self::VISIBILITY_NOBODY;
    }
}
