<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Api\Http\Middleware\LimitAuthenticatedApiRequests;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Each API and OAuth request, before authentication: a generous
        // ceiling per IP address, against floods and token guessing.
        RateLimiter::for('api', static fn (Request $request) => Limit::perMinute((int) config('rate-limiting.api.per_ip_per_minute'))
            ->by('api:ip:' . $request->ip()));

        // {@see LimitAuthenticatedApiRequests} also limits API
        // requests per client or user, after authentication.

        // The MCP server's limits are in App\Mcp\McpServiceProvider.

        RateLimiter::for('auth:impersonate', static fn (Request $request) => Limit::perMinute((int) config('rate-limiting.auth.impersonate.per_minute'))
            ->by($request->user()?->id ?: $request->ip()));

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
