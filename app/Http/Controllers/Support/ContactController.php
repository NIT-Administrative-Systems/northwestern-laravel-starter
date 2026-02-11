<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Domains\Support\Actions\CreateSupportTicket;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Repositories\SupportTicketRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ContactFormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('support.contact.create', [
            'limitedSupportAlert' => config('support.limited_support_warning'),
        ]);
    }

    public function store(
        ContactFormRequest $request,
        SupportTicketRepository $repo,
        CreateSupportTicket $createTicket,
    ): RedirectResponse {
        $ticket = $repo->create($request->user(), new SupportTicket($request->validated()));

        $ticket = $createTicket($ticket);

        $message = match (true) {
            $ticket->wasPostedSuccessfully() => sprintf(
                'Your support request has been submitted (%s). You will receive a confirmation email shortly.',
                $ticket->ticket_number,
            ),
            $ticket->fallback_sent_at !== null => 'Your request has been submitted. You should receive a confirmation email shortly.',
            default => 'Your request has been submitted. Our team will follow up with you by email.',
        };

        return redirect()->route('support.contact.create')->with('status-success', $message);
    }
}
