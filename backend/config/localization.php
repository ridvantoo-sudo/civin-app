<?php

use App\Support\SupportedLocales;

return [
    'supported' => SupportedLocales::CODES,
    'rtl' => SupportedLocales::RTL,
    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),
];
