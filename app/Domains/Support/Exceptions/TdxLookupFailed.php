<?php

declare(strict_types=1);

namespace App\Domains\Support\Exceptions;

use RuntimeException;

/**
 * Thrown when a TeamDynamix metadata lookup (type, form, status, etc.) fails
 * to find a matching record by name.
 */
class TdxLookupFailed extends RuntimeException
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
