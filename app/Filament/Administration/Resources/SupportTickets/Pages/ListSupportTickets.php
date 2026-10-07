<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\SupportTickets\Pages;

use App\Filament\Administration\Resources\SupportTickets\SupportTicketResource;
use App\Filament\Administration\Resources\SupportTickets\Widgets\SupportTicketSubmissionLogBanner;
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
