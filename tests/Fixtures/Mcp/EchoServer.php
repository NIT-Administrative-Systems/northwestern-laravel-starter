<?php

declare(strict_types=1);

namespace Tests\Fixtures\Mcp;

use App\Mcp\Servers\AppServer;

/**
 * The application's MCP server with a tool, for tests that call one. Bind it in place of
 * {@see AppServer}: `$this->app->bind(AppServer::class, EchoServer::class)`.
 */
class EchoServer extends AppServer
{
    protected array $tools = [
        EchoTool::class,
    ];
}
