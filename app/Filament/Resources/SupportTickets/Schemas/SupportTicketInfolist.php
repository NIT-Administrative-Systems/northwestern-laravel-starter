<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportTickets\Schemas;

use App\Domains\Support\Enums\TicketSystem;
use App\Domains\Support\Models\SupportTicket;
use App\Filament\Resources\Users\UserResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class SupportTicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(1)
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 2,
                            ])
                            ->schema([
                                Section::make('Details')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->schema([
                                        TextEntry::make('subject')
                                            ->weight(FontWeight::SemiBold)
                                            ->columnSpanFull(),
                                        TextEntry::make('details')
                                            ->html()
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Delivery')
                                    ->icon(Heroicon::OutlinedPaperAirplane)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('ticketing_system')
                                            ->label('Gateway')
                                            ->badge()
                                            ->formatStateUsing(fn (?TicketSystem $state) => $state?->getLabel() ?? '—'),

                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->getStateUsing(function (SupportTicket $record): string {
                                                if (! $record->post_error) {
                                                    return 'Sent';
                                                }

                                                return $record->fallback_sent_at ? 'Fallback Sent' : 'Failed';
                                            })
                                            ->icon(function (SupportTicket $record): Heroicon {
                                                if (! $record->post_error) {
                                                    return Heroicon::OutlinedCheckCircle;
                                                }

                                                return $record->fallback_sent_at
                                                    ? Heroicon::OutlinedExclamationTriangle
                                                    : Heroicon::OutlinedXCircle;
                                            })
                                            ->color(function (SupportTicket $record): string {
                                                if (! $record->post_error) {
                                                    return 'success';
                                                }

                                                return $record->fallback_sent_at ? 'warning' : 'danger';
                                            }),

                                        TextEntry::make('ticket_number')
                                            ->label('Ticket #')
                                            ->fontFamily(FontFamily::Mono)
                                            ->copyable()
                                            ->placeholder('—'),

                                        TextEntry::make('posted_to_ticketing_system_at')
                                            ->label('Delivered At')
                                            ->since()
                                            ->dateTimeTooltip()
                                            ->placeholder('—'),

                                        TextEntry::make('error_message')
                                            ->label('Error')
                                            ->visible(fn (SupportTicket $record) => $record->post_error)
                                            ->color('danger')
                                            ->icon(Heroicon::OutlinedExclamationCircle)
                                            ->columnSpanFull(),

                                        TextEntry::make('fallback_sent_at')
                                            ->label('Fallback Email')
                                            ->since()
                                            ->dateTimeTooltip()
                                            ->visible(fn (SupportTicket $record) => $record->post_error)
                                            ->placeholder('Not sent')
                                            ->icon(fn (SupportTicket $record) => $record->fallback_sent_at
                                                ? Heroicon::OutlinedCheckCircle
                                                : Heroicon::OutlinedXCircle)
                                            ->color(fn (SupportTicket $record) => $record->fallback_sent_at ? 'warning' : 'danger'),
                                    ]),
                            ]),

                        Section::make('Submitter')
                            ->icon(Heroicon::OutlinedUser)
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 1,
                            ])
                            ->schema([
                                TextEntry::make('user.full_name')
                                    ->label('Name')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->url(fn (SupportTicket $record) => $record->user
                                        ? UserResource::getUrl('view', ['record' => $record->user])
                                        : null)
                                    ->openUrlInNewTab(),

                                TextEntry::make('user.username')
                                    ->label('NetID')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->fontFamily(FontFamily::Mono)
                                    ->color('gray'),

                                TextEntry::make('requester_email')
                                    ->label('Email')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->url(fn (SupportTicket $record) => $record->requester_email
                                        ? "mailto:{$record->requester_email}"
                                        : null)
                                    ->color('primary'),

                                TextEntry::make('created_at')
                                    ->label('Submitted')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->since()
                                    ->dateTimeTooltip()
                                    ->color('gray'),
                            ]),
                    ]),
            ]);
    }
}
