<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportTickets\Widgets;

use Filament\Widgets\Widget;

class SupportTicketSubmissionLogBanner extends Widget
{
    protected string $view = 'filament.resources.support-tickets.widgets.submission-log-banner';

    protected int|string|array $columnSpan = 'full';
}
