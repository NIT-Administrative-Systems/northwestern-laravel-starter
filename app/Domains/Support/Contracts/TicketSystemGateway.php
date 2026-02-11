<?php

declare(strict_types=1);

namespace App\Domains\Support\Contracts;

use App\Domains\Support\Gateway\CreationResult;
use App\Domains\Support\Models\SupportTicket;

/**
 * Contract for ticket system integrations.
 *
 * Each implementation is responsible for submitting a support ticket to an
 * external system (or email) and returning a structured result indicating
 * success or failure. Implementations must never throw — errors should be
 * captured and returned via {@see CreationResult::$creationError}.
 */
interface TicketSystemGateway
{
    /**
     * Submit the given support ticket to the external system.
     *
     * @return CreationResult The outcome of the submission attempt, including
     *                        the external ticket number on success or an error
     *                        message on failure.
     */
    public function create(SupportTicket $ticket): CreationResult;
}
