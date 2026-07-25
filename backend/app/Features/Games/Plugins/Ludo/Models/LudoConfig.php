<?php

namespace App\Features\Games\Plugins\Ludo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LudoConfig extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const MODES = ['classic_2', 'classic_3', 'classic_4', 'private', 'friend', 'random', 'live_room', 'voice_room', 'vip', 'tournament'];

    public const MATCHMAKING = ['private', 'friend', 'random', 'live_room', 'voice_room', 'vip', 'tournament'];

    public const TOURNAMENT_FORMATS = ['single_elimination', 'double_elimination', 'round_robin'];

    protected $table = 'ludo_configs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'modes_enabled' => 'array', 'matchmaking_enabled' => 'array', 'tournament_formats_enabled' => 'array', 'rules' => 'array', 'metadata' => 'array', 'enabled' => 'boolean', 'is_default' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return $this->enabled && $this->status === self::STATUS_ACTIVE;
    }
}
