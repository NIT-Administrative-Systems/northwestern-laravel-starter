<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Which browser origins may call the API from JavaScript. Integrations call from
    | servers, where CORS doesn't apply, so no origin is allowed by default. List
    | origins in CORS_ALLOWED_ORIGINS, separated by commas, to let any browser
    | application on one of those origins call the API with a bearer token.
    |
    | Credentials such as cookies are never allowed, because the API takes bearer
    | tokens only. The OAuth consent page is a page the browser navigates to,
    | never a cross-origin request, so it doesn't need to be listed here.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    // Any origin may call these, without credentials (App\Http\Middleware\HandleCors):
    // the MCP server, its discovery documents, client registration, and Passport's
    // token endpoint, which browser-based MCP clients call directly, though each
    // one still needs its own access token or authorization code to be used.
    'open_paths' => ['mcp', '.well-known/oauth-protected-resource*', '.well-known/oauth-authorization-server*', 'oauth/register', 'oauth/token'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Trace-Id', 'Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'WWW-Authenticate', 'Mcp-Session-Id'],

    'max_age' => 0,

    'supports_credentials' => false,

];
