<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Roles\Tables;

use App\Domains\Auth\Models\Concerns\AuditsPermissions;
use App\Domains\Auth\Models\RoleType;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\Core\Models\Audit;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Northwestern\FilamentTheme\Filters\DateRangeFilter;

/**
 * @phpstan-import-type PermissionData from AuditsPermissions
 */
class RoleDefinitionHistoryTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn () => Audit::query())
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'impersonator']))
            ->columns([
                Split::make([
                    TextColumn::make('event')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => AuditEvent::labelFor($state))
                        ->icon(fn (string $state): Heroicon => AuditEvent::iconFor($state))
                        ->color(fn (string $state): string => AuditEvent::colorFor($state))
                        ->grow(false)
                        ->extraAttributes(['class' => 'min-w-[10rem]']),

                    TextColumn::make('changes_summary')
                        ->label('Changes')
                        ->state(fn (Audit $record) => self::summarizeChanges($record))
                        ->html()
                        ->wrap(),

                    TextColumn::make('modified_by')
                        ->label('Modified By')
                        ->state(function (Audit $record): HtmlString {
                            if (! $record->user) {
                                return new HtmlString('<span class="italic text-gray-500 dark:text-gray-400">System</span>');
                            }

                            return new HtmlString(e("{$record->user->full_name} ({$record->user->username})"));
                        })
                        ->html()
                        ->description(
                            fn (Audit $record) => $record->impersonator
                                ? "Impersonated by {$record->impersonator->full_name}"
                                : null
                        )
                        ->color(fn (Audit $record) => $record->impersonator ? 'warning' : null)
                        ->url(
                            fn (Audit $record) => $record->user
                            ? route('filament.administration.resources.users.view', ['record' => $record->user])
                            : null
                        )
                        ->grow(false)
                        ->extraAttributes(['class' => 'w-56']),

                    TextColumn::make('created_at')
                        ->label('Date')
                        ->since()
                        ->dateTimeTooltip()
                        ->grow(false)
                        ->extraAttributes(['class' => 'w-32']),
                ])->extraAttributes([
                    'x-on:click' => 'if (!$event.target.closest(\'a\')) isCollapsed = ! isCollapsed',
                    'class' => 'cursor-pointer',
                ]),

                Panel::make([
                    View::make('filament.resources.roles.tables.definition-history-collapsible-content'),
                ])->collapsible(),
            ])
            ->recordClasses('transition-colors hover:bg-gray-50 dark:hover:bg-white/5')
            ->defaultSort('created_at', direction: 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Event')
                    ->multiple()
                    ->options(AuditEvent::options(
                        AuditEvent::Created,
                        AuditEvent::Updated,
                        AuditEvent::Deleted,
                        AuditEvent::Restored,
                        AuditEvent::PermissionsModified,
                    ))
                    ->native(false)
                    ->searchable()
                    ->preload(),

                resolve(DateRangeFilter::class)->make(
                    name: 'created_at_range',
                    label: 'Date Range',
                    column: 'created_at',
                    mode: DateRangeFilter::ModeDateTime,
                    icon: Heroicon::Calendar,
                    limitUntilToToday: true,
                )
                    ->columnSpan(2),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->emptyStateHeading('No Changes Yet')
            ->emptyStateDescription('Changes to this role\'s name, type, and permissions will appear here.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    /**
     * Generate a human-readable summary of what changed in an audit entry.
     */
    public static function summarizeChanges(Audit $audit): HtmlString
    {
        $html = match (AuditEvent::tryFrom($audit->event)) {
            AuditEvent::Created => self::badge('Role Created', 'success'),
            AuditEvent::Deleted => self::badge('Role Deleted', 'danger'),
            AuditEvent::Restored => self::badge('Role Restored', 'success'),
            AuditEvent::Updated => self::summarizeAttributeChanges($audit),
            AuditEvent::PermissionsModified => self::summarizePermissionChanges($audit),
            default => '<span class="text-sm text-gray-500">No details</span>',
        };

        return new HtmlString($html);
    }

    /**
     * Summarize standard attribute changes (name, role_type_id, etc.).
     */
    private static function summarizeAttributeChanges(Audit $audit): string
    {
        $oldValues = $audit->old_values ?? [];
        $newValues = $audit->new_values ?? [];

        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allKeys as $key) {
            $oldValue = $oldValues[$key] ?? null;
            $newValue = $newValues[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $label = self::attributeLabel($key);
            $formattedOld = self::formatAttributeValue($key, $oldValue);
            $formattedNew = self::formatAttributeValue($key, $newValue);

            if ($oldValue === null) {
                $changes[] = '<span class="text-sm">'
                    . e($label) . ' set to '
                    . self::valueBadge($formattedNew, 'success')
                    . '</span>';
            } elseif ($newValue === null) {
                $changes[] = '<span class="text-sm">'
                    . e($label) . ' '
                    . self::valueBadge($formattedOld, 'danger')
                    . ' cleared</span>';
            } else {
                $changes[] = '<span class="text-sm">'
                    . e($label) . ': '
                    . self::valueBadge($formattedOld, 'danger')
                    . ' ' . svg('heroicon-m-arrow-right', 'inline h-3 w-3 text-gray-400')->toHtml() . ' '
                    . self::valueBadge($formattedNew, 'success')
                    . '</span>';
            }
        }

        return filled($changes)
            ? '<div class="flex flex-col gap-1">' . implode('', $changes) . '</div>'
            : self::badge('Role Updated', 'gray');
    }

    /**
     * Summarize permission changes by diffing old/new permission arrays.
     */
    private static function summarizePermissionChanges(Audit $audit): string
    {
        /** @var list<PermissionData> $oldPermissionData */
        $oldPermissionData = $audit->old_values['permissions'] ?? [];
        /** @var list<PermissionData> $newPermissionData */
        $newPermissionData = $audit->new_values['permissions'] ?? [];

        $oldPermissions = collect($oldPermissionData);
        $newPermissions = collect($newPermissionData);

        $oldNames = $oldPermissions->pluck('name')->all();
        $newNames = $newPermissions->pluck('name')->all();

        $addedNames = array_diff($newNames, $oldNames);
        $removedNames = array_diff($oldNames, $newNames);

        $added = $newPermissions
            ->filter(fn (array $p) => in_array($p['name'], $addedNames, true))
            ->pluck('label')
            ->all();

        $removed = $oldPermissions
            ->filter(fn (array $p) => in_array($p['name'], $removedNames, true))
            ->pluck('label')
            ->all();

        $parts = [];

        if (filled($added)) {
            $parts[] = self::formatPermissionGroup($added, 'success', '+');
        }

        if (filled($removed)) {
            $parts[] = self::formatPermissionGroup($removed, 'danger', '−');
        }

        return filled($parts)
            ? '<div class="flex flex-col gap-1">' . implode('', $parts) . '</div>'
            : self::badge('Permissions Modified', 'gray');
    }

    /**
     * Format a group of permission changes, showing up to 3 names inline
     * with a "+N more" overflow for larger sets.
     *
     * @param  list<string>  $labels
     */
    private static function formatPermissionGroup(array $labels, string $color, string $prefix): string
    {
        $maxVisible = 3;
        $count = count($labels);
        $visible = array_slice($labels, 0, $maxVisible);
        $remaining = $count - $maxVisible;

        $parts = array_map(fn (string $label) => self::badge($prefix . ' ' . $label, $color), $visible);

        if ($remaining > 0) {
            $overflowLabels = array_slice($labels, $maxVisible);
            $tooltip = e(implode(', ', $overflowLabels));
            $parts[] = '<span title="' . $tooltip . '">'
                . self::badge('+' . $remaining . ' more', 'gray')
                . '</span>';
        }

        return '<div class="flex flex-wrap items-center gap-1">' . implode('', $parts) . '</div>';
    }

    /**
     * Render an inline value badge for attribute diffs.
     */
    private static function valueBadge(string $value, string $color): string
    {
        $colors = match ($color) {
            'success' => 'text-success-700 dark:text-success-400',
            'danger' => 'text-danger-700 dark:text-danger-400 line-through',
            default => 'text-gray-700 dark:text-gray-300',
        };

        return '<span class="font-medium ' . $colors . '">' . e($value) . '</span>';
    }

    /**
     * Get a human-readable label for a model attribute.
     */
    private static function attributeLabel(string $key): string
    {
        return match ($key) {
            'name' => 'Name',
            'role_type_id' => 'Role Type',
            'assignment_locked' => 'Assignment Locked',
            'guard_name' => 'Guard',
            default => Str::of($key)->replace('_', ' ')->title()->toString(),
        };
    }

    /**
     * Format an attribute value for display, resolving foreign keys where possible.
     */
    private static function formatAttributeValue(string $key, mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if ($key === 'role_type_id') {
            // Load every role type once per request instead of querying for each diffed value.
            $roleType = once(fn () => RoleType::all()->keyBy('id'))->get($value);

            return $roleType?->slug?->getLabel() ?? (string) $value;
        }

        if ($key === 'assignment_locked') {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }

    /**
     * Filament's badge, for HTML the column builds itself.
     */
    private static function badge(string $label, string $color, string $class = ''): string
    {
        return Blade::render(
            '<x-filament::badge :color="$color" size="sm" :class="$class">{{ $label }}</x-filament::badge>',
            ['label' => $label, 'color' => $color, 'class' => $class],
        );
    }
}
