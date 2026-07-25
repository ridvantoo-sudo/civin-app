<?php

namespace App\Features\Ai\Support;

final class AiJobType
{
    public const MODERATE_TEXT = 'moderate_text';

    public const MODERATE_VOICE = 'moderate_voice';

    public const TRANSLATE = 'translate';

    public const RECOMMEND = 'recommend';

    public const HIGHLIGHT_SUGGEST = 'highlight_suggest';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::MODERATE_TEXT,
            self::MODERATE_VOICE,
            self::TRANSLATE,
            self::RECOMMEND,
            self::HIGHLIGHT_SUGGEST,
        ];
    }
}
