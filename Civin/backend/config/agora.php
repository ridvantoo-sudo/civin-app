<?php

return [
    'app_id' => env('AGORA_APP_ID'),
    'app_certificate' => env('AGORA_APP_CERTIFICATE'),
    'token_ttl' => (int) env('AGORA_TOKEN_TTL', 3600),
];
