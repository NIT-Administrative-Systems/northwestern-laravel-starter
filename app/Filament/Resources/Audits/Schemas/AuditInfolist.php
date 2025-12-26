<?php

declare(strict_types=1);

namespace App\Filament\Resources\Audits\Schemas;

use App\Domains\User\Models\Audit;
use App\Filament\Resources\Users\UserResource;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Phiki\Grammar\Grammar;

class AuditInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('event')
                            ->label('Action')
                            ->badge()
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->formatStateUsing(fn (string $state) => Str::of($state)->replace('_', ' ')->title()->toString())
                            ->icon(fn (string $state) => match ($state) {
                                'created' => Heroicon::OutlinedPlusCircle,
                                'deleted' => Heroicon::OutlinedMinusCircle,
                                'updated' => Heroicon::OutlinedPencilSquare,
                                'restored' => Heroicon::OutlinedArrowUturnLeft,
                                'role_assigned' => Heroicon::OutlinedUserPlus,
                                'role_removed' => Heroicon::OutlinedUserMinus,
                                'permissions_modified' => Heroicon::OutlinedShieldCheck,
                                default => Heroicon::OutlinedTag,
                            })
                            ->color(fn (string $state) => match ($state) {
                                'created', 'restored', 'role_assigned' => 'success',
                                'deleted', 'role_removed' => 'danger',
                                'updated', 'permissions_modified' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('auditable_type')
                            ->label('Object')
                            ->formatStateUsing(function (Audit $record) {
                                $className = Relation::getMorphedModel($record->auditable_type) ?? $record->auditable_type;

                                return Str::afterLast($className, '\\') ?: $className;
                            })
                            ->badge()
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->color('info')
                            ->icon(Heroicon::OutlinedCube)
                            ->tooltip(fn (string $state) => $state)
                            ->suffix(fn (Audit $record) => $record->auditable_id ? " : {$record->auditable_id}" : null),

                        TextEntry::make('created_at')
                            ->label('When')
                            ->size(TextSize::Large)
                            ->dateTime()
                            ->sinceTooltip()
                            ->icon(Heroicon::OutlinedClock)
                            ->color('gray'),
                    ]),

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
                                    ->columns()
                                    ->schema([
                                        TextEntry::make('user.username')
                                            ->label('Modified By User')
                                            ->formatStateUsing(fn (Audit $r) => $r->user?->full_name
                                                ? "{$r->user->full_name} ({$r->user->username})"
                                                : ($r->user->username ?? '—'))
                                            ->icon(Heroicon::OutlinedUser)
                                            ->url(fn (Audit $r) => $r->user ? UserResource::getUrl('view', ['record' => $r->user]) : null)
                                            ->openUrlInNewTab(),

                                        TextEntry::make('impersonator.username')
                                            ->label('Impersonated By')
                                            ->visible(fn (Audit $record) => filled($record->impersonator))
                                            ->formatStateUsing(fn (Audit $record) => $record->impersonator?->full_name
                                                ? "{$record->impersonator->full_name} ({$record->impersonator->username})"
                                                : ($record->impersonator->username ?? '—'))
                                            ->icon(Heroicon::OutlinedUser)
                                            ->color('warning')
                                            ->url(fn (Audit $record) => $record->impersonator ? UserResource::getUrl('view', ['record' => $record->impersonator]) : null)
                                            ->openUrlInNewTab(),

                                        TextEntry::make('url')
                                            ->label('URL')
                                            ->fontFamily(FontFamily::Mono)
                                            ->color('primary')
                                            ->formatStateUsing(fn ($state) => $state ? str_replace(config('app.url'), '', $state) : '—')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Differences')
                                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                                    ->columns()
                                    ->schema([
                                        CodeEntry::make('old_values')
                                            ->label('Previous Values')
                                            ->placeholder('No previous values')
                                            ->grammar(Grammar::Json)
                                            ->copyable()
                                            ->columnSpanFull(),
                                        CodeEntry::make('new_values')
                                            ->label('New Values')
                                            ->placeholder('No new values')
                                            ->grammar(Grammar::Json)
                                            ->copyable()
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Section::make('Metadata')
                            ->icon(Heroicon::OutlinedInformationCircle)
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 1,
                            ])
                            ->schema([
                                TextEntry::make('trace_id')
                                    ->label('Trace ID')
                                    ->visible(fn (Audit $record) => filled($record->trace_id))
                                    ->icon(Heroicon::OutlinedHashtag)
                                    ->fontFamily(FontFamily::Mono)
                                    ->tooltip('Correlate this change with API request logs and other events using the same Trace ID')
                                    ->copyable()
                                    ->color('gray'),

                                TextEntry::make('tags')
                                    ->label('Tags')
                                    ->placeholder('No tags')
                                    ->badge()
                                    ->separator(',')
                                    ->icon(Heroicon::OutlinedTag)
                                    ->color('gray'),

                                TextEntry::make('ip_address')
                                    ->label('IP Address')
                                    ->placeholder('—')
                                    ->icon(Heroicon::OutlinedGlobeAlt)
                                    ->copyable()
                                    ->color('gray'),

                                TextEntry::make('user_agent')
                                    ->label('User Agent')
                                    ->placeholder('—')
                                    ->icon(Heroicon::OutlinedComputerDesktop)
                                    ->tooltip(fn ($state) => $state)
                                    ->color('gray'),
                            ]),
                    ]),
            ]);
    }
}
