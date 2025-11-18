<?php

declare(strict_types=1);

namespace App\Domains\User\Exceptions;

use Exception;

class BadDirectoryEntry extends Exception
{
    /**
     * @param  array<string, mixed>|null  $directoryData
     */
    public function __construct(
        protected ?string $netId,
        protected ?array $directoryData,
    ) {
        parent::__construct(sprintf('Search for [%s] found in directory, but data was invalid.', $this->netId));
    }
}
