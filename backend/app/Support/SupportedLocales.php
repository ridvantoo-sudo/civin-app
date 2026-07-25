<?php

namespace App\Support;

final class SupportedLocales
{
    /** @var list<string> */
    public const CODES = ['en', 'ar', 'ckb', 'kmr', 'tr', 'fa', 'de', 'fr'];

    /** @var list<string> */
    public const RTL = ['ar', 'fa', 'ckb', 'kmr'];

    public static function isSupported(?string $locale): bool
    {
        if ($locale === null || $locale === '') {
            return false;
        }

        $normalized = strtolower(str_replace('_', '-', $locale));
        $language = explode('-', $normalized)[0];

        return in_array($language, self::CODES, true);
    }

    public static function normalize(?string $locale, string $fallback = 'en'): string
    {
        if ($locale === null || $locale === '') {
            return $fallback;
        }

        $normalized = strtolower(str_replace('_', '-', $locale));
        $language = explode('-', $normalized)[0];

        return in_array($language, self::CODES, true) ? $language : $fallback;
    }

    public static function isRtl(string $locale): bool
    {
        return in_array(self::normalize($locale), self::RTL, true);
    }
}
