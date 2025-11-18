<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Core\ValueObjects\ApiRequestContext;
use App\Domains\User\Models\ApiRequestLog;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Lottery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Captures and persists metadata for API requests.
 *
 * This middleware records request/response details for authenticated API traffic
 * and any request that produced an API failure reason. It runs after the
 * authentication middleware, so the log entry can include the resolved
 * user, API token ID, status code, duration, and failure reason.
 *
 * Successful requests may be sampled based on configuration; failures are
 * always logged. Framework-level exceptions that bypass the middleware
 * stack will not be logged here.
 */
class LogsApiRequests
{
    /**
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure(Request): (Response)  $next  The next middleware in the pipeline
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.api.request_logging.enabled')) {
            return $next($request);
        }

        $startTime = microtime(true);

        $response = $next($request);

        $endTime = microtime(true);
        $durationMs = (int) (($endTime - $startTime) * 1000);

        $response->headers->set('X-Trace-Id', Context::get(ApiRequestContext::TRACE_ID));

        $userId = Context::get(ApiRequestContext::USER_ID);
        $failureReason = Context::get(ApiRequestContext::FAILURE_REASON);
        $statusCode = $response->getStatusCode();

        // Skip logging completely unauthenticated requests without a failure reason
        if (! $userId && ! $failureReason) {
            return $response;
        }

        if (! $this->shouldLogRequest($statusCode, $failureReason)) {
            return $response;
        }

        try {
            $responseBytes = null;
            if (! $response instanceof StreamedResponse) {
                $contentLength = $response->headers->get('Content-Length');

                $responseBytes = $contentLength !== null
                    ? (int) $contentLength
                    : strlen((string) $response->getContent());
            }

            ApiRequestLog::create([
                'trace_id' => Context::get(ApiRequestContext::TRACE_ID),
                'user_id' => $userId,
                'user_api_token_id' => Context::get(ApiRequestContext::TOKEN_ID),
                'method' => $request->method(),
                'path' => $request->path(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip() ?? 'unknown',
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
                'response_bytes' => $responseBytes,
                'user_agent' => $request->userAgent(),
                'failure_reason' => $failureReason,
            ]);
        } catch (Exception) {
            // Silently ignore logging failures to avoid impacting the API response
        }

        return $response;
    }

    /**
     * Determines if the current request should be logged using probabilistic sampling.
     *
     * Sampling allows you to log a representative subset of successful requests while always
     * capturing errors. This maintains observability while controlling storage growth.
     *
     * **Sampling Rules:**
     * 1. If sampling is disabled → Always log all requests
     * 2. If status code ≥ 400 → Always log (errors are always important)
     * 3. If authentication failure → Always log (security events are always important)
     * 4. Otherwise → Apply probabilistic sampling to successful requests
     *
     * @param  int  $statusCode  HTTP status code of the response
     * @param  string|null  $failureReason  Authentication failure reason, if any
     * @return bool True if the request should be logged, false if it should be skipped
     */
    private function shouldLogRequest(int $statusCode, ?string $failureReason): bool
    {
        $samplingEnabled = (bool) config('auth.api.request_logging.sampling.enabled');

        if (! $samplingEnabled) {
            return true;
        }

        // Always log errors and failures regardless of sampling configuration.
        if ($statusCode >= 400 || $failureReason !== null) {
            return true;
        }

        $sampleRate = config('auth.api.request_logging.sampling.rate');
        $sampleRate = max(0.0, min(1.0, $sampleRate));

        if ($sampleRate <= 0.0) {
            return false; // 0%: never log successful requests
        }

        if ($sampleRate >= 1.0) {
            return true; // 100%: always log successful requests
        }

        $numerator = (int) round($sampleRate * 100);
        $denominator = 100;

        return Lottery::odds($numerator, $denominator)
            ->winner(static fn () => true)
            ->loser(static fn () => false)
            ->choose();
    }
}
