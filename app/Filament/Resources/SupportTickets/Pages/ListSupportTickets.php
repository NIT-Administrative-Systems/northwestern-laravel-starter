<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Filament\Resources\SupportTickets\Widgets\SupportTicketSubmissionLogBanner;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\Widget;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    protected ?string $subheading = 'Requests sent through Contact Support.';

    /** @return array<class-string<Widget>> */
    protected function getHeaderWidgets(): array
    {
        return [
            SupportTicketSubmissionLogBanner::class,
        ];
    }
}
