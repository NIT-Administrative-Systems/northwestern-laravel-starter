<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleActivity\Tables;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\User;
use App\Domains\User\Support\UserOptionLabel;
use App\Domains\User\Support\UserSearch;
use App\Filament\Exports\RoleActivityExporter;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Filament\Support\Formatting\BadgePillRenderer;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RoleActivityTable
{
    public static function configure(Table $table, bool $isRelationManager = false): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'role_assigned' => 'Role Assigned',
                        'role_removed' => 'Role Removed',
                        default => $state,
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'role_assigned' => Heroicon::OutlinedUserPlus,
                        'role_removed' => Heroicon::OutlinedUserMinus,
                        default => Heroicon::OutlinedTag,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'role_assigned' => 'success',
                        'role_removed' => 'danger',
                        default => 'gray',
                    })
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
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return resolve(UserSearch::class)->applyToRelation($query, 'auditable', $search, includeEmail: false, boolean: 'or');
                    })
                    ->url(
                        fn (Audit $record) => $record->auditable
                        ? route('filament.administration.resources.users.view', ['record' => $record->auditable])
                        : null
                    )
                    ->hidden($isRelationManager),

                TextColumn::make('changed_roles')
                    ->label('Role')
                    ->state(fn (Audit $record) => new HtmlString(self::formatChangedRoles($record))),

                TextColumn::make('tags')
                    ->label('Origin')
                    ->badge()
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
                    ->state(function (Audit $record): string {
                        return $record->user
                            ? $record->user->full_name
                            : 'System';
                    })
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
                    ->sortable(),
            ])
            ->recordClasses('transition-colors hover:bg-gray-50 dark:hover:bg-white/5')
            ->defaultSort('created_at', direction: 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Event')
                    ->options([
                        'role_assigned' => 'Role Assigned',
                        'role_removed' => 'Role Removed',
                    ])
                    ->searchable()
                    ->preload(),

                SelectFilter::make('role')
                    ->label('Role')
                    ->options(
                        fn () => Role::withTrashed()
                            ->orderBy('name')
                            ->pluck('name', 'name')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->where(function (Builder $q) use ($value) {
                            $q->where('old_values', 'like', '%"name":"' . $value . '"%')
                                ->orWhere('new_values', 'like', '%"name":"' . $value . '"%');
                        });
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
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->recordActions([
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
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Export')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->exporter(RoleActivityExporter::class),
            ])
            ->emptyStateHeading('No role activity recorded')
            ->emptyStateDescription('Role assignments and removals will appear here as administrators manage user roles.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    /**
     * Format the changed roles for display as HTML badges.
     *
     * Uses the pill() pattern from RoleDefinitionHistoryTable for consistent
     * Filament-styled badges with proper dark mode support.
     */
    private static function formatChangedRoles(Audit $record): string
    {
        $changedRoles = $record->getChangedRoles();

        if (empty($changedRoles)) {
            return '<span class="text-sm text-gray-400 dark:text-gray-500">—</span>';
        }

        $existingRoleIds = once(fn () => Role::pluck('id')->all());

        $maxVisible = 3;
        $count = count($changedRoles);
        $visible = array_slice($changedRoles, 0, $maxVisible);
        $remaining = $count - $maxVisible;

        $parts = array_map(function (array $role) use ($existingRoleIds) {
            $roleExists = in_array($role['id'], $existingRoleIds);
            $roleType = collect(RoleTypeEnum::cases())
                ->first(fn (RoleTypeEnum $case) => $case->getLabel() === $role['role_type']);

            if (! $roleExists) {
                $tooltip = e(json_encode(['content' => 'This role has been deleted.', 'theme' => 'light']));

                return '<span x-tooltip="' . $tooltip . '">'
                    . resolve(BadgePillRenderer::class)->render($role['name'], 'gray', 'line-through opacity-60')
                    . '</span>';
            }

            $pill = resolve(BadgePillRenderer::class)->render($role['name'], $roleType?->getColor() ?? 'gray');
            $url = route('filament.administration.resources.roles.view', ['record' => $role['id']]);

            return '<a href="' . e($url) . '">' . $pill . '</a>';
        }, $visible);

        if ($remaining > 0) {
            $overflowRoles = array_slice($changedRoles, $maxVisible);
            $tooltip = implode(', ', array_column($overflowRoles, 'name'));
            $parts[] = '<span x-tooltip="' . e(json_encode(['content' => $tooltip, 'theme' => 'light'])) . '">'
                . resolve(BadgePillRenderer::class)->render('+' . $remaining . ' more', 'gray')
                . '</span>';
        }

        return '<div class="flex flex-wrap items-center gap-1">' . implode('', $parts) . '</div>';
    }
}
