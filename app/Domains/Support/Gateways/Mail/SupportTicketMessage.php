<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateways\Mail;

use App\Domains\Support\Models\SupportTicket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;

/**
 * Email sent to the support team with the ticket details.
 *
 * Contains the submitter's name, the full request body, and — when in fallback
 * mode — a warning that the primary gateway failed. This is an internal email;
 * the user-facing confirmation is handled by {@see SupportTicketConfirmation}.
 */
class SupportTicketMessage extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected SupportTicket $ticket,
        protected string $referenceNumber,
        protected bool $isFallbackEmail,
    ) {
        //
    }

    /**
     * @return $this
     */
    public function build(): static
    {
        $user = $this->ticket->user;

        return $this->subject(sprintf(
            '[%s - %s] %s',
            $this->referenceNumber,
            ucfirst((string) config('app.env')),
            $this->ticket->subject,
        ))
            ->markdown('mail.support.ticket-message')
            ->with([
                'referenceNumber' => $this->referenceNumber,
                'subject' => $this->ticket->subject,
                'details' => $this->ticket->details,
                'submitterName' => $user->full_name,
                'submitterEmail' => $user->email,
                'submitterUsername' => $user->username,
                'submitterDepartments' => $user->departments ?? [],
                'submitterAffiliation' => $user->primary_affiliation?->getLabel(),
                'environment' => ucfirst((string) config('app.env')),
                'fallbackMode' => $this->isFallbackEmail,
            ]);
    }
}
