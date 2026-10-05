<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Auth\Http\Middleware\LimitAuthenticatedApiRequests;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Every API and OAuth request, before authentication: a generous per-IP ceiling against
        // floods and token guessing.
        RateLimiter::for('api', static function (Request $request) {
            return Limit::perMinute((int) config('rate-limiting.api.per_ip_per_minute'))
                ->by('api:ip:' . $request->ip());
        });

        // Authenticated API requests are also limited per client or user, after authentication,
        // by {@see LimitAuthenticatedApiRequests}.

        // MCP client registration needs no credentials, so it gets a tight per-IP limit.
        RateLimiter::for('mcp-registration', static function (Request $request) {
            return Limit::perHour((int) config('mcp.rate_limits.registrations_per_hour'))
                ->by('mcp:registration:' . $request->ip());
        });

        // MCP tool calls, per person. Other MCP messages (listing tools, pings) only count
        // towards the per-IP limit.
        RateLimiter::for('mcp-tool-calls', static function (Request $request) {
            return $request->json('method') === 'tools/call'
                ? Limit::perMinute((int) config('mcp.rate_limits.tool_calls_per_minute'))->by('mcp:tool-calls:' . $request->user()?->getAuthIdentifier())
                : Limit::none();
        });

        RateLimiter::for('auth:impersonate', static function (Request $request) {
            return Limit::perMinute((int) config('rate-limiting.auth.impersonate.per_minute'))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('support:contact', static function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute((int) config('rate-limiting.support.contact.per_minute'))->by('support:min:' . $key),
                Limit::perHour((int) config('rate-limiting.support.contact.per_hour'))->by('support:hr:' . $key),
                Limit::perDay((int) config('rate-limiting.support.contact.per_day'))->by('support:day:' . $key),
            ];
        });
    }
}
