<?php

namespace App\Features\Games\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const SLUG_LUCKY_WHEEL = 'lucky_wheel';

    public const SLUG_LUCKY_DICE = 'lucky_dice';

    public const SLUG_LUCKY_NUMBER = 'lucky_number';

    public const SLUG_TREASURE_BOX = 'treasure_box';

    public const SLUG_RED_ENVELOPE = 'red_envelope';

    public const SLUG_QUIZ = 'quiz';

    public const SLUG_LOTTERY = 'lottery';

    public const SLUG_SLOT_MACHINE = 'slot_machine';

    public const SLUG_ROCK_PAPER_SCISSORS = 'rock_paper_scissors';

    public const SLUG_LUDO = 'ludo';

    /** @return list<string> */
    public static function slugs(): array
    {
        return [
            self::SLUG_LUCKY_WHEEL,
            self::SLUG_LUCKY_DICE,
            self::SLUG_LUCKY_NUMBER,
            self::SLUG_TREASURE_BOX,
            self::SLUG_RED_ENVELOPE,
            self::SLUG_QUIZ,
            self::SLUG_LOTTERY,
            self::SLUG_SLOT_MACHINE,
            self::SLUG_ROCK_PAPER_SCISSORS,
            self::SLUG_LUDO,
        ];
    }

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'category',
        'min_bet_coins',
        'max_bet_coins',
        'house_edge_bps',
        'supports_multiplayer',
        'status',
        'sort_order',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'min_bet_coins' => 'integer',
            'max_bet_coins' => 'integer',
            'house_edge_bps' => 'integer',
            'supports_multiplayer' => 'boolean',
            'sort_order' => 'integer',
            'config' => 'array',
        ];
    }

    public function plays(): HasMany
    {
        return $this->hasMany(GamePlay::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
