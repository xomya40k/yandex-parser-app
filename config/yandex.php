<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Parser page walk (YandexMapParserService)
    |--------------------------------------------------------------------------
    |
    | max_pages — hard cap on ?page=N walks (~600 reviews ⇒ ~12 pages).
    |
    */

    'max_pages' => (int) env('YANDEX_PARSER_MAX_PAGES', 12),

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

    /*
    |--------------------------------------------------------------------------
    | Reparse cadence (SyncYandexOrganizationsCommand)
    |--------------------------------------------------------------------------
    |
    | Ready organizations are re-parsed when last_parsed_at is older than this
    | many hours. Failed ones retry when updated_at is older than the same window.
    |
    */

    'reparse_interval_hours' => (int) env('YANDEX_REPARSE_INTERVAL_HOURS', 24),

];
