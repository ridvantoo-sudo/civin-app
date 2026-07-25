<?php

namespace App\Features\Levels\Http\Resources;

use App\Features\Levels\Models\UserLevelProgress;
use App\Features\Levels\Support\XpCurve;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserLevelProgress */
final class LevelProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $xpToNext = XpCurve::xpRequiredForLevel((int) $this->level);
        $progressPercent = $xpToNext === 0
            ? 100.0
            : round(((int) $this->xp_into_level / $xpToNext) * 100, 2);

        return [
            'user_id' => $this->user_id,
            'level' => (int) $this->level,
            'max_level' => XpCurve::maxLevel(),
            'xp_total' => (int) $this->xp_total,
            'xp_into_level' => (int) $this->xp_into_level,
            'xp_to_next' => $xpToNext,
            'progress_percent' => min(100.0, $progressPercent),
            'is_max_level' => (int) $this->level >= XpCurve::maxLevel(),
        ];
    }
}
