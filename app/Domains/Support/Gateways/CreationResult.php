<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateways;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystem;

/**
 * What happened when a gateway submitted a ticket.
 *
 * Every TicketSystemGateway returns one. When creationError is true,
 * ticketNumber is null and errorMessage explains what went wrong.
 *
 * @see TicketSystemGateway
 */
readonly class CreationResult
{
    /**
     * @param  TicketSystem  $ticketSystemType  The gateway that handled the request.
     * @param  bool  $creationError  Whether the submission failed.
     * @param  non-empty-string|null  $ticketNumber  The external ticket reference (e.g., TDX ID or SUP-47). Null on failure.
     * @param  string|null  $errorMessage  A description of the failure. Null on success.
     */
    public function __construct(
        public TicketSystem $ticketSystemType,
        public bool $creationError,
        public ?string $ticketNumber,
        public ?string $errorMessage,
    ) {
        //
    }
}
