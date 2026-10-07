<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable the MCP Server
    |--------------------------------------------------------------------------
    |
    | The MCP server at `/mcp` lets AI clients such as Claude and VS Code
    | use the application's tools as the person who connects them.
    | Off by default. When false, the server, its OAuth discovery
    | documents and client registration all answer 404.
    |
    */

    'enabled' => (bool) env('MCP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Native Client Redirect Schemes
    |--------------------------------------------------------------------------
    |
    | MCP clients register themselves. A client's redirect URIs must use
    | HTTPS, or HTTP to the person's own computer. Desktop clients that
    | return through their own URI scheme (RFC 8252) need it listed
    | here. None by default: most clients don't need one.
    |
    */

    'custom_schemes' => array_values(array_filter(array_map('trim', explode(',', (string) env('MCP_CUSTOM_SCHEMES', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Client registration is limited per IP address. Tool calls are limited
    | per person, on top of the API's per-IP limit for every request.
    |
    */

    'rate_limits' => [
        'registrations_per_hour' => (int) env('MCP_REGISTRATIONS_PER_HOUR', 20),
        'tool_calls_per_minute' => (int) env('MCP_TOOL_CALLS_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Self-Registered Client Cleanup
    |--------------------------------------------------------------------------
    |
    | `mcp:prune-clients` runs daily. It deletes a self-registered client
    | nobody connected within `unconnected_hours` of registering, and
    | revokes one nobody has used for `unused_days`.
    |
    */

    'cleanup' => [
        'unconnected_hours' => 24,
        'unused_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel MCP
    |--------------------------------------------------------------------------
    |
    | Read by laravel/mcp. `authorization_server` is the OAuth issuer shown in
    | the discovery documents; null means this application's URL.
    |
    */

    'authorization_server' => env('MCP_AUTHORIZATION_SERVER'),

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

];
