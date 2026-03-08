<?php

return [
    'base_url' => env('CHATWOOT_BASE_URL', 'http://localhost'),
    'api_token' => env('CHATWOOT_API_TOKEN'),
    'access_token_header' => env('CHATWOOT_ACCESS_TOKEN_HEADER', 'Api-Access-Token'),
    'timeout' => env('CHATWOOT_HTTP_TIMEOUT', 10),
    'retry_times' => env('CHATWOOT_HTTP_RETRY_TIMES', 3),
    'retry_delay_ms' => env('CHATWOOT_HTTP_RETRY_DELAY_MS', 200),
];
