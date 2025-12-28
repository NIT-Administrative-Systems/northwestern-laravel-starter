<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domains\Auth\Models\ApiRequestLog;
use App\Domains\Core\ValueObjects\ApiRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Context;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 9457 compliant problem details response builder.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9457
 */
#[OA\Schema(
    schema: 'ProblemDetails',
    description: 'Standard RFC 9457 problem details envelope.',
    required: ['type', 'title', 'status', 'instance'],
    properties: [
        new OA\Property(property: 'type', type: 'string', format: 'uri', example: 'about:blank'),
        new OA\Property(property: 'title', type: 'string', example: 'Unauthorized'),
        new OA\Property(property: 'status', type: 'integer', example: 401),
        new OA\Property(property: 'detail', type: 'string', example: 'Authentication failed', nullable: true),
        new OA\Property(property: 'instance', type: 'string', format: 'uri-reference', example: '/api/v1/me'),
        new OA\Property(
            property: 'trace_id',
            description: 'Unique identifier for the API request (if available).',
            type: 'string',
            example: 'b4f5aa7a-1470-4d92-8d3c-98e7c7de9f5f',
            nullable: true
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ValidationProblemDetails',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/ProblemDetails'),
        new OA\Schema(
            properties: [
                new OA\Property(
                    property: 'errors',
                    description: 'Validation errors keyed by input field.',
                    type: 'object',
                    example: [
                        'name' => ['The name field is required.'],
                        'expires_at' => ['The expires at must be at least 24 hours from now.'],
                    ],
                    additionalProperties: new OA\AdditionalProperties(
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    )
                ),
            ]
        ),
    ]
)]
class ProblemDetails
{
    /**
     * Create an RFC 9457 problem details response.
     *
     * All fields are optional per RFC 9457, but we provide sensible defaults.
     *
     * @param  int  $status  HTTP status code (advisory; included for consumer convenience)
     * @param  string  $title  Short, human-readable summary (should not change between occurrences)
     * @param  string|null  $detail  Specific explanation for this occurrence (should help client correct the problem)
     * @param  string  $type  URI reference identifying the problem type (defaults to "about:blank")
     * @param  string|null  $instance  URI identifying this specific occurrence (defaults to current request path)
     * @param  array<string, mixed>  $extensions  Additional problem-specific extension fields
     * @param  array<string, string>  $headers  Additional HTTP headers
     */
    public static function response(
        int $status,
        string $title,
        ?string $detail = null,
        string $type = 'about:blank',
        ?string $instance = null,
        array $extensions = [],
        array $headers = []
    ): JsonResponse {
        $extensions = self::withTraceId($extensions);

        $body = array_merge(
            [
                'type' => $type,
                'title' => $title,
                'status' => $status,
            ],
            $detail ? ['detail' => $detail] : [],
            ['instance' => $instance ?? request()->getRequestUri()],
            $extensions
        );

        return response()->json(
            $body,
            $status,
            array_merge(['Content-Type' => 'application/problem+json'], $headers)
        );
    }

    /**
     * Attach the current API trace ID (if present) to the problem details extensions.
     *
     * This is useful for correlating responses with persisted {@see ApiRequestLog}s.
     *
     * Note: A trace ID is only available when the request reached the API authentication
     * middleware. Framework-level exceptions that occur earlier in the pipeline will not
     * have a trace ID populated and will omit this field.
     *
     * @param  array<string, mixed>  $extensions
     * @return array<string, mixed>
     */
    private static function withTraceId(array $extensions): array
    {
        $traceId = Context::get(ApiRequestContext::TRACE_ID);

        if ($traceId === null) {
            return $extensions;
        }

        return array_merge($extensions, [
            'trace_id' => $traceId,
        ]);
    }

    /**
     * Common problem details responses.
     */
    public static function unauthorized(string $detail = 'Authentication failed', array $headers = []): JsonResponse
    {
        // Default WWW-Authenticate header (can be overridden by passing same key in $headers)
        $defaultHeaders = [
            'WWW-Authenticate' => 'Bearer realm="' . config('auth.api.auth_realm') . '"',
        ];

        return self::response(
            status: Response::HTTP_UNAUTHORIZED,
            title: 'Unauthorized',
            detail: $detail,
            headers: array_merge($defaultHeaders, $headers)
        );
    }

    public static function forbidden(string $detail = 'Access forbidden', array $headers = []): JsonResponse
    {
        return self::response(
            status: Response::HTTP_FORBIDDEN,
            title: 'Forbidden',
            detail: $detail,
            headers: $headers
        );
    }

    public static function notFound(string $detail = 'Resource not found', array $headers = []): JsonResponse
    {
        return self::response(
            status: Response::HTTP_NOT_FOUND,
            title: 'Not Found',
            detail: $detail,
            headers: $headers
        );
    }

    public static function serviceUnavailable(string $detail = 'Service temporarily unavailable', int $retryAfter = 3600, array $headers = []): JsonResponse
    {
        $defaultHeaders = ['Retry-After' => $retryAfter];

        return self::response(
            status: Response::HTTP_SERVICE_UNAVAILABLE,
            title: 'Service Unavailable',
            detail: $detail,
            headers: array_merge($defaultHeaders, $headers)
        );
    }

    public static function tooManyRequests(string $detail = 'Too many requests', int $retryAfter = 60, array $headers = []): JsonResponse
    {
        return self::response(
            status: Response::HTTP_TOO_MANY_REQUESTS,
            title: 'Too Many Requests',
            detail: $detail,
            headers: array_merge(['Retry-After' => $retryAfter], $headers)
        );
    }

    public static function unprocessableEntity(string $detail = 'Validation failed', array $errors = [], array $headers = []): JsonResponse
    {
        return self::response(
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            title: 'Unprocessable Entity',
            detail: $detail,
            extensions: $errors ? ['errors' => $errors] : [],
            headers: $headers
        );
    }

    public static function badRequest(string $detail = 'Bad request', array $extensions = [], array $headers = []): JsonResponse
    {
        return self::response(
            status: Response::HTTP_BAD_REQUEST,
            title: 'Bad Request',
            detail: $detail,
            extensions: $extensions,
            headers: $headers
        );
    }

    /**
     * @param  array|string  $allowedMethods  Array of methods ['GET', 'POST'] or comma-separated string "GET,POST"
     */
    public static function methodNotAllowed(array|string $allowedMethods = [], string $detail = 'Method not allowed', array $headers = []): JsonResponse
    {
        if (is_array($allowedMethods)) {
            $allow = implode(', ', $allowedMethods);
        } else {
            $allow = $allowedMethods;
        }

        $allowHeaders = $allow ? ['Allow' => $allow] : [];

        return self::response(
            status: Response::HTTP_METHOD_NOT_ALLOWED,
            title: 'Method Not Allowed',
            detail: $detail,
            headers: array_merge($allowHeaders, $headers)
        );
    }

    public static function conflict(string $detail = 'Conflict', array $extensions = [], array $headers = []): JsonResponse
    {
        return self::response(
            status: Response::HTTP_CONFLICT,
            title: 'Conflict',
            detail: $detail,
            extensions: $extensions,
            headers: $headers
        );
    }

    public static function payloadTooLarge(
        string $detail = 'Payload too large',
        array $headers = []
    ): JsonResponse {
        return self::response(
            status: Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
            title: 'Payload Too Large',
            detail: $detail,
            headers: $headers
        );
    }

    public static function internalServerError(string $detail = 'An unexpected error occurred', array $headers = []): JsonResponse
    {
        return self::response(
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
            title: 'Internal Server Error',
            detail: $detail,
            headers: $headers
        );
    }
}
