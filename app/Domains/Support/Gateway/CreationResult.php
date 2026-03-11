<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateway;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystemEnum;

/**
 * Immutable value object representing the outcome of a gateway submission.
 *
 * Returned by every {@see TicketSystemGateway} implementation. When {@see $creationError}
 * is true, {@see $ticketNumber} will be null and {@see $errorMessage} will contain
 * a human-readable description of the failure.
 */
readonly class CreationResult
{
    /**
     * @param  TicketSystemEnum  $ticketSystemType  The gateway that handled the request.
     * @param  bool  $creationError  Whether the submission failed.
     * @param  non-empty-string|null  $ticketNumber  The external ticket reference (e.g., TDX ID or SUP-47). Null on failure.
     * @param  string|null  $errorMessage  A description of the failure. Null on success.
     */
    public function __construct(
        public TicketSystemEnum $ticketSystemType,
        public bool $creationError,
        public ?string $ticketNumber,
        public ?string $errorMessage,
    ) {
        //
    }
}
