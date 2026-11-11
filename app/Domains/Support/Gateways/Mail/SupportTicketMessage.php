<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateways\Mail;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Support\Models\SupportTicket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Northwestern\SysDev\Chassis\Formatting\NorthwesternDateTime;

/**
 * The support team's email with a support request.
 *
 * As the fallback, it warns that the ticket system didn't create the
 * ticket. The requester's own copy is SupportTicketConfirmation.
 *
 * @see SupportTicketConfirmation
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
                'details' => $this->ticket->detailsHtml(),
                'submitterName' => $user->full_name ?? 'Unknown',
                'submitterEmail' => $user->email ?? 'Unknown',
                'submitterUsername' => $user->username ?? 'Unknown',
                'submitterUsernameLabel' => $user?->auth_type === AuthType::SSO ? 'NetID' : 'Username',
                'submitterDepartments' => $user->departments ?? [],
                'submitterAffiliation' => $user?->primary_affiliation?->getLabel(),
                'environment' => ucfirst((string) config('app.env')),
                'submittedAt' => NorthwesternDateTime::format($this->ticket->created_at ?? now(), config('app.schedule_timezone')),
                'fallbackMode' => $this->isFallbackEmail,
            ]);
    }
}
