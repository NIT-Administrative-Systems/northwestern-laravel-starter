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
 * Submits support tickets via email.
 *
 * Sends the ticket details to the support team mailbox and a separate
 * confirmation to the requester. Used as the primary driver when
 * {@see TicketSystem::Mail} is configured, or as the auto
 * fallback when a non-mail primary gateway fails.
 *
 * When operating in fallback mode ({@see $isFallbackStrategy}), the support
 * team email includes a warning that the primary gateway failed.
 */
class MailGateway implements TicketSystemGateway
{
    /**
     * @param  bool  $isFallbackStrategy  Whether this instance is being used as a fallback
     *                                    after a primary gateway failure.
     */
    public function __construct(
        protected bool $isFallbackStrategy = false,
    ) {
        //
    }

    /**
     * Send the ticket via email and return a structured result.
     *
     * Dispatches two queued emails: one to the support team ({@see SupportTicketMessage})
     * and one to the requester ({@see SupportTicketConfirmation}). On success, the
     * {@see CreationResult::$ticketNumber} is the mail-generated reference (e.g., "SUP-47").
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
     * Generate a human-readable reference number for the mail gateway.
     *
     * This is the mail gateway's equivalent of an external ticket ID.
     * Other gateways (e.g., TeamDynamix) should return their own
     * native references.
     */
    private function generateReference(SupportTicket $ticket): string
    {
        return sprintf('%s-%d', config('support.mail.reference_prefix', 'SUP'), $ticket->id);
    }
}
