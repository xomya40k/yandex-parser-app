<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Outbound HTTP (YandexMapsIntegrationClient)
    |--------------------------------------------------------------------------
    |
    | request_delay_ms — base pacing delay before each request; the client
    | applies jitter around this value. Set to 0 in tests to skip sleeping.
    |
    */

    'request_delay_ms' => (int) env('YANDEX_PARSER_REQUEST_DELAY_MS', 500),

    'timeout' => (int) env('YANDEX_PARSER_TIMEOUT', 20),

    'user_agent' => env(
        'YANDEX_PARSER_USER_AGENT',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    ),

    'proxy' => env('YANDEX_HTTP_PROXY'),

];
