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

    protected ?string $subheading = 'Submission log for user-submitted support tickets';

    /** @return array<class-string<Widget>> */
    protected function getHeaderWidgets(): array
    {
        return [
            SupportTicketSubmissionLogBanner::class,
        ];
    }
}
