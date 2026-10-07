<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateways\Mail;

use App\Domains\Support\Models\SupportTicket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Northwestern\SysDev\Chassis\Formatting\NorthwesternDateTime;

/**
 * User-facing confirmation email sent after a support ticket is submitted.
 *
 * Contains the reference number, subject, submission time, the user's own details,
 * and next-step expectations.
 * Excludes any internal details (fallback warnings, error messages, etc.).
 * Sent by the {@see MailGateway} for both primary and fallback submissions.
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
