<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Auth\Http\Middleware\AuthenticatesAccessTokens;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The limiter runs before the token middleware authenticates the request, so it asks the
        // middleware for the token's user; without a valid token the request is limited by IP.
        RateLimiter::for('api', static function (Request $request) {
            return Limit::perMinute((int) config('rate-limiting.api.per_minute'))
                ->by(resolve(AuthenticatesAccessTokens::class)->userIdForRateLimiting($request) ?? $request->ip());
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
