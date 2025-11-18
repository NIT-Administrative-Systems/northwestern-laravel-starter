<?php

declare(strict_types=1);

namespace App\Domains\Core\Exceptions;

use App\Domains\Core\Enums\ApiRequestFailureEnum;
use App\Domains\Core\ValueObjects\ApiRequestContext;
use App\Http\Middleware\LogsApiRequests;
use App\Http\Responses\ProblemDetails;
use ErrorException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use PDOException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

/**
 * Converts API exceptions into RFC 9457 Problem Details responses and optionally
 * tags failures for enriched request logging.
 *
 * Notes:
 * - Only applies to API requests (under `/api/*` or requests that want JSON)
 * - Tags certain application-level exceptions with an {@see ApiRequestFailureEnum}
 *   so that {@see LogsApiRequests} can associate the failure with a user
 * - Framework-level routing exceptions (e.g. 404, some 405/400 errors throw before
 *   the middleware stack) still receive Problem Details responses, but will not
 *   produce API request logs because no authentication or logging middleware is
 *   executed for them
 */
class ProblemDetailsRenderer
{
    /**
     * Maps various exceptions to RFC 9457 Problem Details responses.
     */
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->wantsJson()) {
            return null;
        }

        return match (true) {
            // --- 4XX Client Errors ---
            $e instanceof ValidationException => tap(
                ProblemDetails::unprocessableEntity(errors: $e->errors()),
                fn () => $this->setFailure(ApiRequestFailureEnum::VALIDATION_FAILED)
            ),

            // 401 & 403 Authentication/Authorization
            $e instanceof AuthenticationException => ProblemDetails::unauthorized(),

            $e instanceof UnauthorizedHttpException,
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => tap(
                ProblemDetails::forbidden(
                    detail: $e->getMessage() ?: 'You do not have permission to access this resource.'
                ),
                fn () => $this->setFailure(ApiRequestFailureEnum::UNAUTHORIZED)
            ),

            // 404 Not Found & 409 Conflict
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ProblemDetails::notFound(),

            $e instanceof ConflictHttpException => tap(
                ProblemDetails::conflict(
                    detail: $e->getMessage() ?: 'Conflict'
                ),
                fn () => $this->setFailure(ApiRequestFailureEnum::CONFLICT)
            ),

            // 405 Method Not Allowed
            $e instanceof MethodNotAllowedHttpException => ProblemDetails::methodNotAllowed(
                allowedMethods: $e->getHeaders()['Allow'] ?? [],
                detail: 'The HTTP method used is not supported for this endpoint.'
            ),

            // 400 Bad Request
            $e instanceof BadRequestException,
            $e instanceof ErrorException,
            $e instanceof NotAcceptableHttpException => ProblemDetails::badRequest(
                detail: $e->getMessage() ?: 'The request could not be understood by the server.'
            ),

            // 413 Payload Too Large
            $e instanceof PostTooLargeException => ProblemDetails::payloadTooLarge(),

            // 429 Rate Limiting
            $e instanceof ThrottleRequestsException => ProblemDetails::tooManyRequests(
                detail: 'Too many requests. Please try again later.',
                retryAfter: $e->getHeaders()['Retry-After'] ?? 60
            ),

            // --- 5XX Server Errors ---
            // 503 Service Unavailable
            $e instanceof ServiceUnavailableHttpException => ProblemDetails::serviceUnavailable(),

            $e instanceof HttpExceptionInterface => tap(
                ProblemDetails::response(
                    status: $e->getStatusCode(),
                    title: 'HTTP Error',
                    detail: $e->getMessage() ?: null,
                    headers: $e->getHeaders()
                ),
                fn () => $this->setFailure(ApiRequestFailureEnum::SERVER_ERROR)
            ),

            // Catch specific database exceptions
            $e instanceof PDOException => tap(
                ProblemDetails::internalServerError(
                    detail: 'A database error occurred while processing the request.'
                ),
                fn () => $this->setFailure(ApiRequestFailureEnum::DATABASE_ERROR)
            ),

            // 500 Internal Server Error
            default => tap(
                ProblemDetails::internalServerError(),
                fn () => $this->setFailure(ApiRequestFailureEnum::SERVER_ERROR)
            ),
        };
    }

    /**
     * Write a failure reason into the shared API request context, but do not
     * overwrite an existing one (e.g. from the authentication middleware).
     */
    private function setFailure(ApiRequestFailureEnum $failure): void
    {
        if (Context::has(ApiRequestContext::FAILURE_REASON)) {
            return;
        }

        Context::add(ApiRequestContext::FAILURE_REASON, $failure->value);
    }
}
