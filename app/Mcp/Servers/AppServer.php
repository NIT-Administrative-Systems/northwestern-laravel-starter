<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Contracts\Transport;

/**
 * The application's MCP server, at `/mcp` (routes/ai.php). It acts as the person who connected
 * the client, so each tool works within that person's permissions. It ships with no tools:
 * generate one with `php artisan make:mcp-tool`, list it here, and hide it from people who
 * may not use it in the tool's `shouldRegister()`.
 */
#[Version('1.0.0')]
#[Instructions('Tools act as the person who connected this client, within their permissions.')]
class AppServer extends Server
{
    protected array $tools = [];

    protected array $resources = [];

    protected array $prompts = [];

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);

        $this->name = (string) config('app.name');
    }
}
