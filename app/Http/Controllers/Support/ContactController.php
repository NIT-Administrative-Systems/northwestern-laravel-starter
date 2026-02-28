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

        if ($ticket->wasPostedSuccessfully()) {
            $message = sprintf(
                'Your support request has been submitted (%s). You will receive a confirmation email shortly.',
                $ticket->ticket_number,
            );

            return redirect()->route('support.contact.create')->with('status-success', $message);
        }

        if ($ticket->fallback_sent_at !== null) {
            return redirect()->route('support.contact.create')->with(
                'status-success',
                'Your request has been submitted. You should receive a confirmation email shortly.',
            );
        }

        return redirect()->route('support.contact.create')->with(
            'status-danger',
            'We were unable to submit your request. Please try again later.',
        );
    }
}
