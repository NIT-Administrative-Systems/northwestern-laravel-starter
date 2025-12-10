<?php

declare(strict_types=1);

namespace App\Domains\Core\Exceptions;

use RuntimeException;

class MissingRequestIpForRestrictedToken extends RuntimeException
{
    public function __construct(
        public readonly array $allowedIps = [],
        string $message = 'Request IP missing for IP-restricted Access Token.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
