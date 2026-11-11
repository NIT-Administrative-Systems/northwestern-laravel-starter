<?php

declare(strict_types=1);

namespace App\Domains\Support\Exceptions;

use RuntimeException;

/**
 * Thrown when no TeamDynamix ticket type, form or status has the configured name.
 */
class TdxLookupFailedException extends RuntimeException
{
    /**
     * @param  string  $apiName  The TDX API entity being looked up (e.g., "Ticket Type", "Service").
     * @param  string  $value  The name that was searched for but not found.
     */
    public static function for(string $apiName, string $value): self
    {
        return new self("Unable to find {$apiName} with value '{$value}' in TeamDynamix.");
    }
}
