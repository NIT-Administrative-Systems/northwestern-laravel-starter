<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Domains\Support\Models\SupportTicket;
use Filament\Actions\Exports\ExportColumn;

class SupportTicketExporter extends BaseExporter
{
    protected static ?string $model = SupportTicket::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('user.full_name')
                ->label('Submitter'),

            ExportColumn::make('user.username')
                ->label('Username'),

            ExportColumn::make('requester_email')
                ->label('Email'),

            ExportColumn::make('subject')
                ->label('Subject'),

            ExportColumn::make('ticketing_system')
                ->label('Gateway'),

            ExportColumn::make('ticket_number')
                ->label('Ticket #'),

            ExportColumn::make('post_error')
                ->label('Failed')
                ->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No'),

            ExportColumn::make('error_message')
                ->label('Error Message')
                ->enabledByDefault(false),

            ExportColumn::make('posted_to_ticketing_system_at')
                ->label('Delivered'),

            ExportColumn::make('fallback_sent_at')
                ->label('Fallback Sent')
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label('Submitted'),
        ];
    }

    protected static function recordNoun(): string
    {
        return 'support ticket';
    }
}
