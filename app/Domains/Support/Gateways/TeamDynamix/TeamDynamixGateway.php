<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateways\TeamDynamix;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystemEnum;
use App\Domains\Support\Exceptions\TdxLookupFailed;
use App\Domains\Support\Gateway\CreationResult;
use App\Domains\Support\Models\SupportTicket;
use InvalidArgumentException;
use Northwestern\Sysdev\TeamDynamix\Api\Entity\Ticket\CreateTicket;
use Northwestern\Sysdev\TeamDynamix\Api\Entity\Ticket\TicketResponse;
use Northwestern\Sysdev\TeamDynamix\Laravel\TeamDynamixService;

/**
 * Submits support tickets to TeamDynamix via the TDX REST API.
 *
 * Resolves metadata IDs (type, form, status, priority, service, etc.) through the
 * {@see TeamDynamixCacheRepository} and creates the ticket with a 3-attempt
 * retry.
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
     * Submit the ticket to TeamDynamix with automatic retry.
     *
     * On success, {@see CreationResult::$ticketNumber} contains the TDX ticket identifier.
     * On failure after 3 attempts, the exception is captured in Sentry and returned
     * as a {@see CreationResult} with {@see CreationResult::$creationError} set.
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

            if (app()->bound('sentry')) {
                resolve('sentry')->captureException($e);
            }
        }

        return new CreationResult(
            ticketSystemType: TicketSystemEnum::TEAM_DYNAMIX,
            creationError: $hasError,
            ticketNumber: $ticketNumber,
            errorMessage: $errorMessage,
        );
    }

    /**
     * Resolve TDX metadata IDs and create the ticket.
     *
     * @throws TdxLookupFailed If any metadata lookup fails.
     */
    private function lookupAndCreate(SupportTicket $ticket): TicketResponse
    {
        $tdxTicket = new CreateTicket(
            typeId: $this->cache->findTicketTypeId(config('support.team-dynamix.ticket_type')),
            formId: $this->cache->findTicketFormTypeId(config('support.team-dynamix.form_type')),
            title: $ticket->subject,
            description: is_array($ticket->details) ? (json_encode($ticket->details) ?: null) : (string) $ticket->details,
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
     * Resolve the TDX assignee group ID from configuration.
     *
     * @return positive-int
     *
     * @throws InvalidArgumentException If the group ID is missing or not a positive integer.
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
