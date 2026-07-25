<?php

namespace App\Features\CreatorTools\Support;

final class ClipKind
{
    public const CLIP = 'clip';

    public const HIGHLIGHT = 'highlight';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::CLIP, self::HIGHLIGHT];
    }
}
