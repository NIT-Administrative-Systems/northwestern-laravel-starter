<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleActivity\Tables;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\User;
use App\Filament\Exports\RoleActivityExporter;
use App\Filament\Resources\Audits\AuditResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\RelationManagers\RoleActivityRelationManager;
use App\Filament\Resources\Users\UserResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RoleActivityTable
{
    public static function configure(Table $table): Table
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
                        return $query
                            ->orWhereHas('auditable', function (Builder $q) use ($search) {
                                $q->where('username', 'ilike', "%{$search}%")
                                    ->orWhere('first_name', 'ilike', "%{$search}%")
                                    ->orWhere('last_name', 'ilike', "%{$search}%");
                            });
                    })
                    ->url(
                        fn (Audit $record) => $record->auditable
                        ? UserResource::getUrl('view', ['record' => $record->auditable])
                        : null
                    )
                    ->hiddenOn(RoleActivityRelationManager::class),

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
                        return $query
                            ->orWhereRelation('user', 'username', 'ilike', "%{$search}%")
                            ->orWhereRelation('impersonator', 'username', 'ilike', "%{$search}%");
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
                    ->hiddenOn(RoleActivityRelationManager::class)
                    ->searchable()
                    ->getSearchResultsUsing(
                        fn (string $search) => User::query()
                            ->where(
                                fn (Builder $q) => $q
                                    ->where('username', 'ilike', "%{$search}%")
                                    ->orWhere('first_name', 'ilike', "%{$search}%")
                                    ->orWhere('last_name', 'ilike', "%{$search}%")
                            )
                            ->orderBy('last_name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (User $user) => [$user->id => "{$user->clerical_name} ({$user->username})"])
                            ->all()
                    )
                    ->getOptionLabelUsing(
                        function (int $value): ?string {
                            $user = User::find($value);

                            return $user ? "{$user->clerical_name} ({$user->username})" : null;
                        }
                    ),

                SelectFilter::make('user_id')
                    ->label('Performed By')
                    ->searchable()
                    ->getSearchResultsUsing(
                        fn (string $search) => User::query()
                            ->where(
                                fn (Builder $q) => $q
                                    ->where('username', 'ilike', "%{$search}%")
                                    ->orWhere('first_name', 'ilike', "%{$search}%")
                                    ->orWhere('last_name', 'ilike', "%{$search}%")
                            )
                            ->orderBy('last_name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (User $user) => [$user->id => "{$user->full_name} ({$user->username})"])
                            ->all()
                    )
                    ->getOptionLabelUsing(
                        function (int $value): ?string {
                            $user = User::find($value);

                            return $user ? "{$user->full_name} ({$user->username})" : null;
                        }
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

                Filter::make('created_at_range')
                    ->label('Date Range')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->native(false)
                            ->prefixIcon(Heroicon::Calendar)
                            ->closeOnDateSelection(),
                        DatePicker::make('to')
                            ->label('To')
                            ->native(false)
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
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->recordActions([
                ViewAction::make()
                    ->label('View Audit')
                    ->url(fn (Audit $record) => AuditResource::getUrl('view', ['record' => $record])),
                Action::make('view_user')
                    ->label('View User')
                    ->icon(Heroicon::OutlinedUser)
                    ->url(
                        fn (Audit $record) => $record->auditable
                        ? UserResource::getUrl('view', ['record' => $record->auditable])
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
                    . self::pill($role['name'], 'gray', 'line-through opacity-60')
                    . '</span>';
            }

            $pill = self::pill($role['name'], $roleType?->getColor() ?? 'gray');
            $url = RoleResource::getUrl('view', ['record' => $role['id']]);

            return '<a href="' . e($url) . '">' . $pill . '</a>';
        }, $visible);

        if ($remaining > 0) {
            $overflowRoles = array_slice($changedRoles, $maxVisible);
            $tooltip = implode(', ', array_column($overflowRoles, 'name'));
            $parts[] = '<span x-tooltip="' . e(json_encode(['content' => $tooltip, 'theme' => 'light'])) . '">'
                . self::pill('+' . $remaining . ' more', 'gray')
                . '</span>';
        }

        return '<div class="flex flex-wrap items-center gap-1">' . implode('', $parts) . '</div>';
    }

    /**
     * Render a small pill/badge matching Filament's fi-badge styling.
     *
     * Reuses the same pattern from RoleDefinitionHistoryTable::pill().
     */
    private static function pill(string $text, string $color, string $extraClasses = ''): string
    {
        $colors = match ($color) {
            'success' => 'fi-color-success bg-success-50 text-success-600 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/20',
            'danger' => 'fi-color-danger bg-danger-50 text-danger-600 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/20',
            'warning' => 'fi-color-warning bg-warning-50 text-warning-600 ring-warning-600/10 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/20',
            'primary' => 'fi-color-primary bg-primary-50 text-primary-600 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20',
            default => 'fi-color-gray bg-gray-50 text-gray-600 ring-gray-600/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20',
        };

        $classes = 'fi-badge fi-size-sm inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ' . $colors;
        if ($extraClasses !== '') {
            $classes .= ' ' . $extraClasses;
        }

        return '<span class="' . $classes . '">' . e($text) . '</span>';
    }
}
