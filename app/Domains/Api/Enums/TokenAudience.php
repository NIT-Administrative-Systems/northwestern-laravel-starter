<?php

declare(strict_types=1);

namespace App\Domains\Api\Enums;

use App\Domains\Api\Http\Middleware\RequireTokenAudience;

/**
 * Where an access token may be used, which its scopes decide ({@see RequireTokenAudience}).
 */
enum TokenAudience: string
{
    /** The REST API: any token except one issued to an MCP client. */
    case Api = 'api';

    /** The MCP server: only a token a person approved for an MCP client, carrying `mcp:use`. */
    case Mcp = 'mcp';
}
