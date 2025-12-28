<?php

declare(strict_types=1);

namespace App\Filament\Resources\Audits\Tables;

use App\Domains\User\Models\Audit;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class AuditsTable
{
    /**
     * Model types that should be filtered out of the table by default.
     *
     * This is useful for:
     * - Models that generate excessive audit entries (e.g., automated processes)
     * - Models whose changes are not relevant for typical audit review
     * - Sensitive models that should only be viewed when specifically needed
     *
     * Users can still view these audits by adjusting table filters.
     *
     * @var list<class-string>
     */
    private const array DEFAULT_IGNORED_MODEL_TYPES = [
        //
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('impersonator'))
            ->columns([
                TextColumn::make('trace_id')
                    ->label('Trace ID')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state) => Str::of($state)->replace('_', ' ')->title()->toString()
                    )
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
                    })
                    ->sortable(),
                TextColumn::make('auditable_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(function (string $state) {
                        $className = Relation::getMorphedModel($state) ?? $state;

                        return Str::afterLast($className, '\\') ?: $className;
                    })
                    ->searchable(),
                TextColumn::make('auditable_id')
                    ->label('Type ID')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('url')
                    ->formatStateUsing(fn ($state) => str_replace(config('app.url'), '', $state))
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('user.username')
                    ->label('NetID')
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query
                            ->orWhereRelation('user', 'username', 'ilike', "%{$search}%")
                            ->orWhereRelation('impersonator', 'username', 'ilike', "%{$search}%");
                    })
                    ->formatStateUsing(fn ($state, $record) => $record->user->username ?? '')
                    ->description(
                        fn ($record) => $record->impersonator
                        ? "Impersonated by {$record->impersonator->username}"
                        : null
                    )
                    ->color(fn ($record) => $record->impersonator ? 'warning' : null),
                TextColumn::make('user.full_name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        /** @phpstan-ignore-next-line  */
                        return $query->orWhereHas('user', fn (Builder $q) => $q->searchByName($search))
                            /** @phpstan-ignore-next-line  */
                            ->orWhereHas('impersonator', fn (Builder $q) => $q->searchByName($search));
                    })
                    ->formatStateUsing(fn ($state, $record) => $record->user->full_name ?? '')
                    ->description(
                        fn ($record) => $record->impersonator
                        ? "Impersonated by {$record->impersonator->full_name}"
                        : null
                    )
                    ->color(fn ($record) => $record->impersonator ? 'warning' : null),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tags')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Event')
                    ->multiple()
                    ->options([
                        'created' => 'Created',
                        'deleted' => 'Deleted',
                        'updated' => 'Updated',
                        'restored' => 'Restored',
                        'role_assigned' => 'Role Assigned',
                        'role_removed' => 'Role Removed',
                        'permissions_modified' => 'Permissions Modified',
                    ]),
                SelectFilter::make('auditable_type')
                    ->label('Type')
                    ->multiple()
                    ->options(self::modelTypeOptionsGrouped())
                    ->searchable()
                    ->preload()
                    ->indicateUsing(function (array $state): ?string {
                        $values = $state['values'] ?? [];

                        if (blank($values)) {
                            return null;
                        }

                        $map = self::modelTypeValueToLabelMap();
                        $labels = collect($values)->map(fn ($v) => $map[$v] ?? $v)->all();

                        return 'Type: ' . implode(', ', $labels);
                    }),

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
                                fn (Builder $q) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($data['from'])->startOfDay())
                            )
                            ->when(
                                filled($data['to'] ?? null),
                                fn (Builder $q) => $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($data['to'])->endOfDay())
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (filled($data['from'] ?? null)) {
                            $indicators[] = 'From: ' . \Illuminate\Support\Carbon::parse($data['from'])->toDateString();
                        }
                        if (filled($data['to'] ?? null)) {
                            $indicators[] = 'To: ' . \Illuminate\Support\Carbon::parse($data['to'])->toDateString();
                        }

                        return $indicators;
                    }),
                Filter::make('exclude_types')
                    ->label('Ignored Types')
                    ->schema([
                        Select::make('types')
                            ->label('Ignored Types')
                            ->hintIcon(Heroicon::OutlinedInformationCircle)
                            ->hintIconTooltip('Some model types create automated or low-value audit logs and are hidden by default. You can include or exclude specific types to narrow results as needed.')
                            ->multiple()
                            ->placeholder('None')
                            ->options(self::modelTypeOptionsGrouped())
                            ->searchable()
                            ->default([...self::DEFAULT_IGNORED_MODEL_TYPES])
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $types = $data['types'] ?? [];

                        return $query->when(filled($types), fn (Builder $q) => $q->whereNotIn('auditable_type', $types));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $types = $data['types'] ?? [];

                        if (blank($types)) {
                            return null;
                        }

                        $map = self::modelTypeValueToLabelMap();
                        $labels = collect($types)->map(fn ($v) => $map[$v] ?? $v)->all();

                        return 'Ignored: ' . implode(', ', $labels);
                    }),

            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }

    /**
     * Grouped options for Filament: "Domain" => [ full_class => short model ]
     *
     * @return array<string,array<string,string>>
     */
    private static function modelTypeOptionsGrouped(): array
    {
        $all = Audit::query()
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type');

        return $all
            ->map(function (string $fullClass) {
                $actual = Relation::getMorphedModel($fullClass) ?? $fullClass;

                if (preg_match('/App\\\\Domains\\\\([^\\\\]+)\\\\Models\\\\[^\\\\]+/', $actual, $m)) {
                    $domain = Str::headline($m[1]);
                } else {
                    $domain = 'Other';
                }

                return [
                    'domain' => $domain,
                    'full_class' => $fullClass,
                    'label' => class_basename($actual),
                ];
            })
            ->groupBy('domain')
            ->map(function ($items) {
                return collect($items)->mapWithKeys(
                    fn ($it) => [$it['full_class'] => $it['label']]
                )->all();
            })
            ->all();
    }

    /**
     * Reverse lookup map for indicators: full_class => short model label
     *
     * @return array<string,string>
     */
    private static function modelTypeValueToLabelMap(): array
    {
        // flatten the grouped options
        return collect(self::modelTypeOptionsGrouped())
            ->flatMap(fn ($grp) => $grp)
            ->all();
    }
}
