<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateways\Mail;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystem;
use App\Domains\Support\Gateways\CreationResult;
use App\Domains\Support\Models\SupportTicket;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Submits support tickets by email.
 *
 * It emails the ticket to the support team and a confirmation to the requester.
 *
 * It's the driver when support.driver is mail, and
 * the fallback when another ticket system fails.
 *
 * As the fallback, the support team's email warns that the ticket system failed.
 */
class MailGateway implements TicketSystemGateway
{
    /**
     * @param  bool  $isFallbackStrategy  Whether another ticket system failed first
     */
    public function __construct(
        protected bool $isFallbackStrategy = false,
    ) {
        //
    }

    /**
     * Queue the support team's email and the requester's confirmation.
     *
     * The ticket number is the gateway's own reference, such as SUP-47.
     */
    public function create(SupportTicket $ticket): CreationResult
    {
        $referenceNumber = $this->generateReference($ticket);

        try {
            Mail::to(config('support.mail.to'))
                ->send(new SupportTicketMessage($ticket, $referenceNumber, $this->isFallbackStrategy));

            Mail::to($ticket->requester_email)
                ->send(new SupportTicketConfirmation($ticket, $referenceNumber));

            return new CreationResult(
                ticketSystemType: TicketSystem::Mail,
                creationError: false,
                ticketNumber: $referenceNumber,
                errorMessage: null,
            );
        } catch (Throwable $e) {
            report($e);

            return new CreationResult(
                ticketSystemType: TicketSystem::Mail,
                creationError: true,
                ticketNumber: null,
                errorMessage: $e->getMessage(),
            );
        }
    }

    /**
     * The mail gateway's reference, such as SUP-47,
     * standing in for a ticket system's number.
     */
    private function generateReference(SupportTicket $ticket): string
    {
        return sprintf('%s-%d', config('support.mail.reference_prefix', 'SUP'), $ticket->id);
    }
}
