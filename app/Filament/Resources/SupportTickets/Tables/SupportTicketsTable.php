<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportTickets\Tables;

use App\Domains\Support\Enums\TicketSystemEnum;
use App\Domains\Support\Models\SupportTicket;
use App\Filament\Exports\SupportTicketExporter;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('user.full_name')
                    ->label('Submitter')
                    ->description(fn (SupportTicket $record) => $record->user?->username)
                    ->searchable(['first_name', 'last_name']),

                TextColumn::make('subject')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (SupportTicket $record) => strlen($record->subject) > 50 ? $record->subject : null),

                TextColumn::make('ticketing_system')
                    ->label('Gateway')
                    ->badge()
                    ->formatStateUsing(fn (?TicketSystemEnum $state) => $state?->getLabel() ?? '—'),

                TextColumn::make('ticket_number')
                    ->label('Ticket #')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (SupportTicket $record): string {
                        if (! $record->post_error) {
                            return 'Sent';
                        }

                        return $record->fallback_sent_at ? 'Fallback Sent' : 'Failed';
                    })
                    ->icon(function (SupportTicket $record) {
                        if (! $record->post_error) {
                            return Heroicon::OutlinedCheckCircle;
                        }

                        return $record->fallback_sent_at ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedXCircle;
                    })
                    ->color(function (SupportTicket $record): string {
                        if (! $record->post_error) {
                            return 'success';
                        }

                        return $record->fallback_sent_at ? 'warning' : 'danger';
                    }),

                TextColumn::make('requester_email')
                    ->label('Email')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->tooltip(fn (SupportTicket $record) => $record->error_message)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('post_error')
                    ->label('Delivery Status')
                    ->trueLabel('Failed')
                    ->falseLabel('Successful')
                    ->placeholder('All'),

                SelectFilter::make('ticketing_system')
                    ->label('Gateway')
                    ->options(TicketSystemEnum::class),

                Filter::make('created_at_range')
                    ->label('Date Range')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->prefixIcon(Heroicon::Calendar)
                            ->closeOnDateSelection(),
                        DatePicker::make('to')
                            ->label('To')
                            ->prefixIcon(Heroicon::Calendar)
                            ->closeOnDateSelection()
                            ->minDate(fn (callable $get) => $get('from'))
                            ->maxDate(Carbon::today()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($data['from'])->startOfDay())
                            )
                            ->when(
                                filled($data['to'] ?? null),
                                fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($data['to'])->endOfDay())
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (filled($data['from'] ?? null)) {
                            $indicators[] = 'From: ' . Carbon::parse($data['from'])->toDateString();
                        }
                        if (filled($data['to'] ?? null)) {
                            $indicators[] = 'To: ' . Carbon::parse($data['to'])->toDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Export')
                    ->exporter(SupportTicketExporter::class),
            ])
            ->emptyStateHeading('No support tickets')
            ->emptyStateDescription('Tickets submitted through the contact form will appear here.')
            ->emptyStateIcon(Heroicon::OutlinedTicket);
    }
}
