<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\Auth\Models\AccessToken;
use App\Domains\Core\Enums\ApiRequestFailureEnum;
use App\Domains\Core\Exceptions\MissingRequestIpForRestrictedToken;
use App\Domains\Core\ValueObjects\ApiRequestContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates API requests using Bearer token authentication with {@see AccessToken} records.
 *
 * This middleware:
 * 1. Validates Bearer token from Authorization header
 * 2. Checks for active Access Tokens and associated API users
 * 3. Enforces IP-based access control if configured on the token
 * 4. Updates the {@see AccessToken::$last_used_at} timestamp and usage count
 * 5. Authenticates the user for the current request
 */
class AuthenticatesAccessTokens
{
    /**
     * Authenticates API requests using Bearer tokens with IP-based access control.
     *
     * This method validates Bearer tokens against stored HMAC-SHA256 hashes and enforces
     * IP allowlists when configured. Failed authentication attempts are logged with
     * specific failure reasons for security monitoring.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure(Request): (Response)  $next  The next middleware in the pipeline
     *
     * @throws AuthenticationException When authentication fails for any reason
     *
     * @see ApiRequestFailureEnum for the specific failure reasons tracked in logs
     * @see AccessToken for token management
     */
    public function handle(Request $request, Closure $next): Response
    {
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        $authHeader = (string) $request->header('Authorization', '');

        if (! str_starts_with($authHeader, 'Bearer ')) {
            $this->fail(ApiRequestFailureEnum::INVALID_HEADER_FORMAT);
        }

        $rawToken = trim(Str::after($authHeader, 'Bearer '));

        if (blank($rawToken)) {
            $this->fail(ApiRequestFailureEnum::MISSING_CREDENTIALS);
        }

        $tokenHash = AccessToken::hashFromPlain($rawToken);
        unset($rawToken);

        $accessToken = AccessToken::query()
            ->withWhereHas('user', fn ($query) => $query->where('auth_type', AuthTypeEnum::API))
            ->where('token_hash', $tokenHash)
            ->active()
            ->first();

        if (! $accessToken || ! $accessToken->user) {
            $this->fail(ApiRequestFailureEnum::TOKEN_INVALID_OR_EXPIRED);
        }

        $user = $accessToken->user;

        Context::add(ApiRequestContext::USER_ID, $user->getKey());
        Context::add(ApiRequestContext::TOKEN_ID, $accessToken->getKey());

        if (! $this->isIpAllowed($request->ip(), $accessToken->allowed_ips)) {
            $this->fail(ApiRequestFailureEnum::IP_DENIED);
        }

        $accessToken->increment(
            column: 'usage_count',
            extra: ['last_used_at' => now()]
        );

        Auth::onceUsingId($user->getKey());

        return $next($request);
    }

    /**
     * Check if the request IP is allowed by the token's IP allowlist.
     *
     * Supports both individual IP addresses and CIDR notation for IP ranges.
     * If no IP restrictions are configured on the token, all IPs are allowed.
     *
     * @param  string|null  $requestIp  The IP address of the incoming request
     * @param  array<string>|null  $allowedIps  List of allowed IPs or CIDR ranges
     * @return bool True if the IP is allowed, false otherwise
     */
    private function isIpAllowed(?string $requestIp, ?array $allowedIps): bool
    {
        // No IP restrictions configured
        if (blank($allowedIps)) {
            return true;
        }

        /**
         * IP restrictions ARE configured, but the request IP is missing.
         * This can happen when the app is behind a proxy or load
         * balancer that does not forward the client IP, or when
         * the request comes from an internal service without
         * a client IP.
         */
        if (blank($requestIp)) {
            report(new MissingRequestIpForRestrictedToken($allowedIps));

            return false;
        }

        // IP restrictions ARE configured and the request IP is present
        return IpUtils::checkIp($requestIp, $allowedIps);
    }

    private function fail(ApiRequestFailureEnum $reason): void
    {
        Context::add(ApiRequestContext::FAILURE_REASON, $reason->value);

        throw new AuthenticationException();
    }
}
