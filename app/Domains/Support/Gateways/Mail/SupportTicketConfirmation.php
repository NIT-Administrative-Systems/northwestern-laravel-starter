<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateways\Mail;

use App\Domains\Support\Models\SupportTicket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;

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
            'We received your support request - %s',
            $this->ticket->subject,
        ))
            ->markdown('mail.support.ticket-confirmation')
            ->with([
                'submitter' => $this->ticket->user->first_name ?? 'User',
                'subject' => $this->ticket->subject,
                'details' => $this->ticket->details,
                'referenceNumber' => $this->referenceNumber,
                'submittedAt' => ($this->ticket->created_at ?? now())
                    ->setTimezone(config('app.schedule_timezone'))
                    ->format('M j, Y g:i A T'),
            ]);
    }
}
