<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystem;
use App\Domains\Support\Gateways\TicketSystemGatewayFactory;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Repositories\SupportTicketRepository;

/**
 * Orchestrates support ticket submission to the configured gateway.
 *
 * Submits the ticket via the primary gateway, records the result, and — if
 * the primary gateway is not mail and fails — automatically falls back to
 * the {@see MailGateway} so the user's request is never silently lost.
 *
 * @see TicketSystemGateway
 */
class CreateSupportTicket
{
    public function __construct(
        protected TicketSystemGateway $gateway,
        protected TicketSystemGatewayFactory $factory,
        protected SupportTicketRepository $repo,
    ) {
        //
    }

    /**
     * Submit the ticket and handle fallback on failure.
     *
     * The returned ticket will have its gateway result fields populated
     * ({@see SupportTicket::$ticketing_system}, {@see SupportTicket::$post_error}, etc.).
     * If a fallback was attempted, {@see SupportTicket::$fallback_sent_at} will be set.
     */
    public function __invoke(SupportTicket $ticket): SupportTicket
    {
        $creationResult = $this->gateway->create($ticket);
        $ticket = $this->repo->updatePostStatus($ticket, $creationResult);

        if ($creationResult->creationError && $creationResult->ticketSystemType !== TicketSystem::Mail) {
            $fallbackResult = $this->factory->fallback()->create($ticket);

            if (! $fallbackResult->creationError) {
                $ticket->update(['fallback_sent_at' => now()]);
            }
        }

        return $ticket;
    }
}
