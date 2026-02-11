<?php

declare(strict_types=1);

namespace App\Domains\Support\Repositories;

use App\Domains\Support\Gateway\CreationResult;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persistence layer for support ticket creation and status updates.
 */
class SupportTicketRepository
{
    /**
     * Persist a new support ticket and associate it with the submitting user.
     *
     * The ticket's {@see SupportTicket::$requester_email} is populated from the
     * user's email at the time of submission for audit purposes.
     */
    public function create(User $user, SupportTicket $ticket): SupportTicket
    {
        return DB::transaction(static function () use ($user, $ticket) {
            $ticket->requester_email = $user->email;
            $user->support_tickets()->save($ticket);

            return $ticket;
        });
    }

    /**
     * Record the gateway's submission result on the ticket.
     *
     * Maps the {@see CreationResult} fields onto the ticket model and sets
     * {@see SupportTicket::$posted_to_ticketing_system_at} on success.
     */
    public function updatePostStatus(SupportTicket $ticket, CreationResult $result): SupportTicket
    {
        return DB::transaction(static function () use ($ticket, $result) {
            $ticket->ticketing_system = $result->ticketSystemType;
            $ticket->ticket_number = $result->ticketNumber;
            $ticket->post_error = $result->creationError;
            $ticket->error_message = $result->errorMessage;

            if (! $result->creationError) {
                $ticket->posted_to_ticketing_system_at = Carbon::now();
            }

            $ticket->save();

            return $ticket;
        });
    }
}
