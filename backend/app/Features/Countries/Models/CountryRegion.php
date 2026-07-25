<?php

namespace App\Features\Countries\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CountryRegion extends Model
{
    use HasUuids, SoftDeletes;

    public const CODE_KURDISTAN_REGION = 'IQ-KRI';

    protected $fillable = ['country_id', 'code', 'name', 'active'];

    protected $appends = ['flag_emoji', 'flag_url'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function isKurdistanRegion(): bool
    {
        return strtoupper((string) $this->code) === self::CODE_KURDISTAN_REGION;
    }

    public function getFlagEmojiAttribute(): ?string
    {
        // No official Unicode regional-indicator flag exists for Kurdistan.
        return null;
    }

    public function getFlagUrlAttribute(): ?string
    {
        if (! $this->isKurdistanRegion()) {
            return null;
        }

        return asset('flags/kurdistan.svg');
    }
}
