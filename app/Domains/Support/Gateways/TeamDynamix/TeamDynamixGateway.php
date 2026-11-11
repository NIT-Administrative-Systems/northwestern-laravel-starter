<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateways\TeamDynamix;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystem;
use App\Domains\Support\Exceptions\TdxLookupFailedException;
use App\Domains\Support\Gateways\CreationResult;
use App\Domains\Support\Models\SupportTicket;
use InvalidArgumentException;
use Northwestern\Sysdev\TeamDynamix\Api\Entity\Ticket\CreateTicket;
use Northwestern\Sysdev\TeamDynamix\Api\Entity\Ticket\TicketResponse;
use Northwestern\Sysdev\TeamDynamix\Laravel\TeamDynamixService;

/**
 * Submits the ticket to TeamDynamix through its REST API.
 *
 * It gets the metadata IDs from TeamDynamixCacheRepository.
 */
class TeamDynamixGateway implements TicketSystemGateway
{
    public function __construct(
        protected TeamDynamixService $tdxApiManager,
        protected TeamDynamixCacheRepository $cache,
    ) {
        //
    }

    /**
     * Submit the ticket, trying three times.
     *
     * The ticket number is the TeamDynamix ticket's ID. A third
     * failure is reported and comes back as a failed result.
     */
    public function create(SupportTicket $ticket): CreationResult
    {
        $ticketNumber = null;
        $hasError = false;
        $errorMessage = null;

        try {
            $tdxTicket = retry(3, fn () => $this->lookupAndCreate($ticket), 250);
            $ticketNumber = (string) $tdxTicket->ticket->id;
        } catch (\Throwable $e) {
            $hasError = true;
            $errorMessage = $e->getMessage();

            report($e);
        }

        return new CreationResult(
            ticketSystemType: TicketSystem::TeamDynamix,
            creationError: $hasError,
            ticketNumber: $ticketNumber,
            errorMessage: $errorMessage,
        );
    }

    /**
     * Resolve the metadata IDs and create the ticket.
     *
     * @throws TdxLookupFailedException
     */
    private function lookupAndCreate(SupportTicket $ticket): TicketResponse
    {
        $tdxTicket = new CreateTicket(
            typeId: $this->cache->findTicketTypeId(config('support.team-dynamix.ticket_type')),
            formId: $this->cache->findTicketFormTypeId(config('support.team-dynamix.form_type')),
            title: $ticket->subject,
            description: $ticket->detailsHtml()->toHtml(),
            statusId: $this->cache->findTicketStatusId(config('support.team-dynamix.ticket_status')),
            priorityId: $this->cache->findTicketPriorityId(config('support.team-dynamix.ticket_priority')),
            requestorEmail: $ticket->requester_email ?? '',
            responsibleGroupId: $this->resolveAssigneeGroupId(),
            serviceId: $this->cache->findServiceId(config('support.team-dynamix.service')),
            isRichHtml: true,
        );

        return $this->tdxApiManager->ticket()->create(
            ticketInfo: $tdxTicket,
            notifyReviewer: false,
            notifyRequestor: true,
            notifyResponsible: true,
            allowRequestorCreation: false,
        );
    }

    /**
     * Get the TeamDynamix group the ticket is assigned to.
     *
     * @return positive-int
     *
     * @throws InvalidArgumentException
     */
    private function resolveAssigneeGroupId(): int
    {
        $groupId = config('support.team-dynamix.assign_to_group');

        if (empty($groupId) || (int) $groupId <= 0) {
            throw new InvalidArgumentException(
                'TDX_ASSIGNEE_ID is required and must be a positive integer when using the TeamDynamix driver.'
            );
        }

        return (int) $groupId;
    }
}
