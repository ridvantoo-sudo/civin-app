<?php

namespace App\Features\Security\Models;

use App\Features\Security\Rules\RuleProvider;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Operator-editable rule row. Rows override the config defaults by `key`;
 * saving one busts the rule cache, which is how hot reload works.
 */
class SecurityRule extends Model
{
    use HasUuids;

    protected $table = 'security_rules';

    protected $fillable = [
        'key',
        'name',
        'description',
        'signal',
        'severity',
        'action',
        'threshold',
        'priority',
        'is_enabled',
        'match_count',
        'conditions',
        'metadata',
        'updated_by',
        'last_matched_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'float',
            'priority' => 'integer',
            'is_enabled' => 'boolean',
            'match_count' => 'integer',
            'conditions' => 'array',
            'metadata' => 'array',
            'last_matched_at' => 'datetime',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Any write invalidates the compiled rule set, so an operator's change is
     * live on the next analysed event without restarting queue workers.
     */
    protected static function booted(): void
    {
        $flush = static function (): void {
            app(RuleProvider::class)->flush();
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
