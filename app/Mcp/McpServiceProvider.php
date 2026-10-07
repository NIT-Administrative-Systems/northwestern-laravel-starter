<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Mcp\Http\Middleware\AddScopeToChallenge;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

/**
 * Wires up the MCP connection: the scope on the server's 401 challenge and the rate limits on
 * registration and tool calls. Its routes are in `routes/ai.php`, and the tools a team builds in
 * `app/Mcp/Tools`, listed in {@see Servers\AppServer}.
 */
class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The 401 challenge also names the scope a client should request.
        $this->app->bind(AddWwwAuthenticateHeader::class, AddScopeToChallenge::class);
    }

    public function boot(): void
    {
        // Registration needs no credentials, so it gets a tight per-IP limit.
        RateLimiter::for('mcp-registration', static fn (Request $request): Limit => Limit::perHour((int) config('mcp.rate_limits.registrations_per_hour'))
            ->by('mcp:registration:' . $request->ip()));

        // Tool calls, per person. Other MCP messages (listing tools, pings) only count towards the
        // API's per-IP limit.
        RateLimiter::for('mcp-tool-calls', static fn (Request $request): Limit => $request->json('method') === 'tools/call'
            ? Limit::perMinute((int) config('mcp.rate_limits.tool_calls_per_minute'))->by('mcp:tool-calls:' . $request->user()?->getAuthIdentifier())
            : Limit::none());
    }
}
