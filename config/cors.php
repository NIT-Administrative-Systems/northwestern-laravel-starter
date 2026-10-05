<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Which browser origins may call the API from JavaScript. Integrations call
    | from servers, where CORS doesn't apply, so no origin is allowed by default.
    | List origins in CORS_ALLOWED_ORIGINS (comma-separated) to allow a browser
    | application to call the API with a bearer token.
    |
    | Credentials (cookies) are never allowed: the API authenticates with bearer
    | tokens only. The OAuth authorization page is a page the browser navigates
    | to, never a cross-origin request, so it is not listed here.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Trace-Id', 'Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],

    'max_age' => 0,

    'supports_credentials' => false,

];
