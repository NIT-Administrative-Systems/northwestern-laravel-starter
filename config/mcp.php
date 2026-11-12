<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable the MCP Server
    |--------------------------------------------------------------------------
    |
    | The MCP server at `/mcp` lets AI clients such as Claude and VS Code use the
    | application's tools as the person who connects them. It's off by default,
    | and while it is off the server, its discovery documents and its client
    | registration endpoint all answer 404, as though they didn't exist.
    |
    */

    'enabled' => (bool) env('MCP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Native Client Redirect Schemes
    |--------------------------------------------------------------------------
    |
    | MCP clients register themselves. A redirect URI must use HTTPS, or HTTP to the
    | person's own computer, and a desktop client that returns through its own URI
    | scheme (RFC 8252) needs that scheme listed here, which most clients don't.
    |
    */

    'custom_schemes' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('MCP_CUSTOM_SCHEMES', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Client Metadata Document Hosts
    |--------------------------------------------------------------------------
    |
    | A client can identify itself with the URL of a metadata document it
    | publishes, instead of registering. The server fetches the document
    | from a host listed here, so the client's name is that host's word
    | and not only its own. Claude uses `claude.ai`. Leave it empty to
    | have every client register itself instead.
    |
    */

    'client_metadata_hosts' => array_values(array_filter(array_map(fn (string $host): string => strtolower(trim($host)), explode(',', (string) env('MCP_CLIENT_METADATA_HOSTS', 'claude.ai'))))),

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
    | revokes one nobody has used for `unused_days` days or more.
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
    | Read by laravel/mcp. `authorization_server` is the OAuth issuer the
    | discovery documents name; null means this application's own URL.
    |
    */

    'authorization_server' => env('MCP_AUTHORIZATION_SERVER'),

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

];
