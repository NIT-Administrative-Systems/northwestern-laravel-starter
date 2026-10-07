<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\SupportTickets\Pages;

use App\Filament\Administration\Resources\SupportTickets\SupportTicketResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    public function getRecordTitle(): string|Htmlable
    {
        return 'Support Ticket #' . $this->record->getKey();
    }
}
