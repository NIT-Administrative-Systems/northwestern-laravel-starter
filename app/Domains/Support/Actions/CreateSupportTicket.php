<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystem;
use App\Domains\Support\Gateways\CreationResult;
use App\Domains\Support\Gateways\Mail\MailGateway;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;

/**
 * Submits a person's support request. It saves it as their ticket, sends it
 * to the configured ticket system and records the result on the ticket.
 *
 * SupportServiceProvider picks the ticket system from support.driver.
 *
 * When a ticket system other than mail fails, the request goes
 * to the support team by email instead, so it's never lost.
 *
 * @see MailGateway
 */
class CreateSupportTicket
{
    public function __construct(
        protected TicketSystemGateway $gateway,
    ) {
        //
    }

    /**
     * The ticket records which system took it, its number there and any
     * error, and fallback_sent_at when the fallback email went out.
     *
     * @param  array<string, mixed>  $request  The Contact Support form's fields
     */
    public function __invoke(User $requester, array $request): SupportTicket
    {
        $ticket = new SupportTicket($request);
        $ticket->requester_email = $requester->email;
        $requester->support_tickets()->save($ticket);

        $result = $this->gateway->create($ticket);
        $this->record($ticket, $result);

        if ($result->creationError && $result->ticketSystemType !== TicketSystem::Mail) {
            $fallback = resolve(MailGateway::class, ['isFallbackStrategy' => true])->create($ticket);

            if (! $fallback->creationError) {
                $ticket->update(['fallback_sent_at' => now()]);
            }
        }

        return $ticket;
    }

    private function record(SupportTicket $ticket, CreationResult $result): void
    {
        $ticket->ticketing_system = $result->ticketSystemType;
        $ticket->ticket_number = $result->ticketNumber;
        $ticket->post_error = $result->creationError;
        $ticket->error_message = $result->errorMessage;

        if (! $result->creationError) {
            $ticket->posted_to_ticketing_system_at = now();
        }

        $ticket->save();
    }
}
