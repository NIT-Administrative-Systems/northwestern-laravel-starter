<?php

declare(strict_types=1);

namespace App\Domains\Core\ValueObjects;

/**
 * Shared context keys used throughout the API request lifecycle.
 *
 * These constants act as identifiers for values stored in Laravel's
 * request-scoped Context bag. They allow middleware, exception
 * rendering, and logging layers to exchange metadata without
 * directly depending on each other.
 */
final readonly class ApiRequestContext
{
    /** Unique request-level identifier shared across all logging. */
    public const string TRACE_ID = 'api_trace_id';

    /** ID of the authenticated API user (if authentication succeeded). */
    public const string USER_ID = 'api_user_id';

    /** ID of the authenticated API token (if token validation succeeded). */
    public const string TOKEN_ID = 'api_token_id';

    /** Readable failure reason set by authentication or exception handling. */
    public const string FAILURE_REASON = 'api_failure_reason';
}
