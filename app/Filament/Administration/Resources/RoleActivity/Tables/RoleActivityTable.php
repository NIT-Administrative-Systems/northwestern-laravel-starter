<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\RoleActivity\Tables;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Models\Role;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Models\User;
use App\Domains\User\Support\UserOptionLabel;
use App\Domains\User\Support\UserSearch;
use App\Filament\Administration\Exports\RoleActivityExporter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Northwestern\FilamentTheme\Filters\DateRangeFilter;

class RoleActivityTable
{
    public static function configure(Table $table, bool $isRelationManager = false): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AuditEvent::labelFor($state))
                    ->icon(fn (string $state): Heroicon => AuditEvent::iconFor($state))
                    ->color(fn (string $state): string => AuditEvent::colorFor($state))
                    ->sortable()
                    ->tooltip(fn (Audit $record) => $record->created_at
                        ->setTimezone(auth()->user()->timezone ?? config('app.timezone'))
                        ->format(config('platform.datetime_display_format', 'M j, Y g:i A'))),

                TextColumn::make('auditable.clerical_name')
                    ->label('User')
                    ->state(function (Audit $record): string {
                        /** @var User|null $user */
                        $user = $record->auditable;

                        return $user->clerical_name ?? '—';
                    })
                    ->description(function (Audit $record): ?string {
                        /** @var User|null $user */
                        $user = $record->auditable;

                        return $user?->username;
                    })
                    ->searchable(query: fn (Builder $query, string $search): Builder => resolve(UserSearch::class)->applyToRelation($query, 'auditable', $search, includeEmail: false, boolean: 'or'))
                    ->url(
                        fn (Audit $record) => $record->auditable
                        ? route('filament.administration.resources.users.view', ['record' => $record->auditable])
                        : null
                    )
                    ->hidden($isRelationManager)
                    ->wrap(),

                TextColumn::make('changed_roles')
                    ->label('Role')
                    ->state(fn (Audit $record): array => array_column($record->getChangedRoles(), 'name'))
                    ->badge()
                    ->color(fn (string $state, Audit $record): string => self::changedRole($record, $state)['color'])
                    // No link of its own: the whole row already links to the audit, and a link can't sit inside another.
                    ->formatStateUsing(fn (string $state, Audit $record): string|HtmlString => self::changedRole($record, $state)['exists']
                        ? $state
                        : new HtmlString('<s>' . e($state) . '</s><span class="sr-only"> (deleted)</span>'))
                    ->tooltip(fn (Audit $record): ?string => collect($record->getChangedRoles())->contains(fn (array $role): bool => ! self::changedRole($record, $role['name'])['exists'])
                        ? 'Struck-through roles have been deleted.'
                        : null)
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->listWithLineBreaks()
                    ->wrap()
                    // Whole role names, wrapped inside the badge instead of widening the table.
                    ->extraAttributes(['class' => 'max-w-48'])
                    ->placeholder('None'),

                TextColumn::make('tags')
                    ->label('Origin')
                    ->badge()
                    ->visibleFrom('2xl')
                    ->formatStateUsing(function (?string $state): string {
                        if (! $state) {
                            return '—';
                        }

                        $originValue = explode(',', $state)[0] ?? $state;
                        $origin = RoleModificationOrigin::tryFrom(trim($originValue));

                        return $origin?->getLabel() ?? $originValue;
                    })
                    ->icon(function (?string $state): ?Heroicon {
                        if (! $state) {
                            return null;
                        }

                        $originValue = explode(',', $state)[0] ?? $state;

                        return RoleModificationOrigin::tryFrom(trim($originValue))?->getIcon();
                    })
                    ->color(function (?string $state): string {
                        if (! $state) {
                            return 'gray';
                        }

                        $originValue = explode(',', $state)[0] ?? $state;
                        $origin = RoleModificationOrigin::tryFrom(trim($originValue));

                        return $origin?->getColor() ?? 'gray';
                    }),

                TextColumn::make('user.full_name')
                    ->label('Performed By')
                    ->visibleFrom('xl')
                    ->state(fn (Audit $record): string => $record->user
                        ? $record->user->full_name
                        : 'System')
                    ->description(function (Audit $record): ?string {
                        if ($record->user) {
                            $desc = $record->user->username;
                            if ($record->impersonator) {
                                $desc .= " (impersonated by {$record->impersonator->username})";
                            }

                            return $desc;
                        }

                        return null;
                    })
                    ->icon(fn (Audit $record) => $record->user
                        ? null
                        : Heroicon::OutlinedCpuChip)
                    ->color(fn (Audit $record) => $record->impersonator ? 'warning' : null)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $userSearch = resolve(UserSearch::class);

                        $userSearch->applyToRelation($query, 'user', $search, includeEmail: false, boolean: 'or');

                        return $userSearch->applyToRelation($query, 'impersonator', $search, includeEmail: false, boolean: 'or');
                    }),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    // Below xl, the event's tooltip gives the date.
                    ->visibleFrom('xl'),
            ])
            ->recordClasses('transition-colors hover:bg-gray-50 dark:hover:bg-white/5')
            ->defaultSort('created_at', direction: 'desc')
            // Each search term adds three user subqueries; match the term as one phrase instead.
            ->splitSearchTerms(false)
            ->searchDebounce('750ms')
            ->filters([
                SelectFilter::make('event')
                    ->label('Event')
                    ->options(AuditEvent::options(AuditEvent::RoleAssigned, AuditEvent::RoleRemoved))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('role')
                    ->label('Role')
                    ->options(
                        fn () => Role::withTrashed()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $roleId = $data['value'] ?? null;

                        if (blank($roleId)) {
                            return $query;
                        }

                        // An audit lists every role the person had before and after the change,
                        // so the role that changed is the one in only one of the two lists.
                        $role = json_encode(['roles' => [['id' => (int) $roleId]]]);

                        return $query->whereRaw(
                            'coalesce(old_values::jsonb @> ?::jsonb, false) <> coalesce(new_values::jsonb @> ?::jsonb, false)',
                            [$role, $role],
                        );
                    }),

                SelectFilter::make('auditable_id')
                    ->label('User')
                    ->hidden($isRelationManager)
                    ->searchable()
                    ->getSearchResultsUsing(
                        fn (string $search): array => resolve(UserSearch::class)->options(
                            search: $search,
                            format: UserOptionLabel::FormatClericalName,
                            withUsername: true,
                            includeEmail: false,
                        )
                    )
                    ->getOptionLabelUsing(
                        fn (int $value): ?string => resolve(UserSearch::class)->label(
                            id: $value,
                            format: UserOptionLabel::FormatClericalName,
                            withUsername: true,
                        )
                    ),

                SelectFilter::make('user_id')
                    ->label('Performed By')
                    ->searchable()
                    ->getSearchResultsUsing(
                        fn (string $search): array => resolve(UserSearch::class)->options(
                            search: $search,
                            format: UserOptionLabel::FormatFullName,
                            withUsername: true,
                            includeEmail: false,
                        )
                    )
                    ->getOptionLabelUsing(
                        fn (int $value): ?string => resolve(UserSearch::class)->label(
                            id: $value,
                            format: UserOptionLabel::FormatFullName,
                            withUsername: true,
                        )
                    ),

                SelectFilter::make('origin')
                    ->label('Origin')
                    ->options(
                        collect(RoleModificationOrigin::cases())
                            ->mapWithKeys(fn (RoleModificationOrigin $origin) => [$origin->value => $origin->getLabel()])
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->where('tags', 'like', "%{$value}%");
                    }),

                resolve(DateRangeFilter::class)->make(
                    name: 'created_at_range',
                    label: 'Date Range',
                    column: 'created_at',
                    mode: DateRangeFilter::ModeDateTime,
                    icon: Heroicon::Calendar,
                    limitUntilToToday: true,
                )
                    ->columns(2)
                    ->columnSpan(2),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['default' => 1, 'md' => 2, 'xl' => 3])
            ->deferLoading()
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('View Audit')
                        ->url(fn (Audit $record) => route('filament.administration.resources.audits.view', ['record' => $record])),
                    Action::make('view_user')
                        ->label('View User')
                        ->icon(Heroicon::OutlinedUser)
                        ->url(
                            fn (Audit $record) => $record->auditable
                            ? route('filament.administration.resources.users.view', ['record' => $record->auditable])
                            : null
                        )
                        ->hidden(fn (Audit $record) => ! $record->auditable),
                ]),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->exporter(RoleActivityExporter::class),
            ])
            ->emptyStateHeading('No Role Activity')
            ->emptyStateDescription('Role assignments and removals appear here.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    /**
     * Whether a role this audit changed still exists, and the color of its role type.
     *
     * @return array{exists: bool, color: string}
     */
    private static function changedRole(Audit $record, string $name): array
    {
        $existingRoleIds = once(fn () => Role::pluck('id')->all());
        $role = collect($record->getChangedRoles())->firstWhere('name', $name);

        if ($role === null || ! in_array($role['id'], $existingRoleIds)) {
            return ['exists' => false, 'color' => 'gray'];
        }

        $roleType = collect(RoleTypeEnum::cases())
            ->first(fn (RoleTypeEnum $case) => $case->getLabel() === $role['role_type']);

        return ['exists' => true, 'color' => $roleType?->getColor() ?? 'gray'];
    }
}
