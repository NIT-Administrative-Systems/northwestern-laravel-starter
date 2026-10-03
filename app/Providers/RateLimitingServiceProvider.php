<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Auth\Models\AccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // No custom ->response(): it would make the throttle throw an HttpResponseException, which the
        // Problem Details renderer turns into a 500. Its ThrottleRequestsException maps to a 429 instead.
        RateLimiter::for('api', static function (Request $request) {
            return Limit::perMinute((int) config('rate-limiting.api.per_minute'))
                ->by(self::accessTokenUserId($request) ?? $request->user()->id ?? $request->ip());
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

    /**
     * The API user behind the request's access token. The `api` limiter runs in the route group,
     * before the token middleware authenticates the request, so it looks the token up itself.
     * Without a valid token the request is limited by IP.
     */
    private static function accessTokenUserId(Request $request): ?int
    {
        $plainToken = $request->bearerToken();

        if (blank($plainToken)) {
            return null;
        }

        $userId = AccessToken::query()
            ->where('token_hash', AccessToken::hashFromPlain($plainToken))
            ->active()
            ->value('user_id');

        return $userId === null ? null : (int) $userId;
    }
}
