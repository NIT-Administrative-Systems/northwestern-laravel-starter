<?php

declare(strict_types=1);

namespace App\Domains\Support\Contracts;

use App\Domains\Support\Gateways\CreationResult;
use App\Domains\Support\Models\SupportTicket;

/**
 * Sends a support ticket to a ticket system or by email.
 *
 * An implementation never throws: it returns a CreationResult saying
 * whether the ticket was created, with the error when it wasn't.
 */
interface TicketSystemGateway
{
    /**
     * Submit the ticket and report what happened.
     */
    public function create(SupportTicket $ticket): CreationResult;
}
