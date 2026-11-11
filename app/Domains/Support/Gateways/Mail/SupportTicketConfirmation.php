<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateways\Mail;

use App\Domains\Support\Models\SupportTicket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Northwestern\SysDev\Chassis\Formatting\NorthwesternDateTime;

/**
 * The requester's confirmation that their support request arrived.
 *
 * It has the reference, subject, submission time and what they sent,
 * and no internal details, such as the fallback warning or errors.
 *
 * @see MailGateway
 */
class SupportTicketConfirmation extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected SupportTicket $ticket,
        protected string $referenceNumber,
    ) {
        //
    }

    public function build(): static
    {
        return $this->subject(sprintf(
            'We received your support request: %s',
            $this->ticket->subject,
        ))
            ->markdown('mail.support.ticket-confirmation')
            ->with([
                'submitter' => $this->ticket->user->first_name ?: $this->ticket->user->full_name,
                'subject' => $this->ticket->subject,
                'details' => $this->ticket->detailsHtml(),
                'referenceNumber' => $this->referenceNumber,
                'submittedAt' => NorthwesternDateTime::format($this->ticket->created_at ?? now(), $this->ticket->user->timezone),
            ]);
    }
}
