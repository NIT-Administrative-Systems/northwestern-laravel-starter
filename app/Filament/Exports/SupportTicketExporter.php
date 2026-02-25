<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Domains\Support\Models\SupportTicket;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class SupportTicketExporter extends Exporter
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
                ->label('Delivered At'),

            ExportColumn::make('fallback_sent_at')
                ->label('Fallback Sent At')
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label('Submitted At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);
        $body = sprintf('Exported %s support %s.', $count, Str::plural('ticket', $export->successful_rows));

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= sprintf(' %s %s failed.', number_format($failedRowsCount), Str::plural('row', $failedRowsCount));
        }

        return $body;
    }
}
