<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Tables;

use App\Domains\Auth\Models\RoleType;
use App\Domains\User\Models\Audit;
use App\Filament\Resources\Users\UserResource;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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
                        ->formatStateUsing(
                            fn (string $state) => Str::of($state)->replace('_', ' ')->title()->toString()
                        )
                        ->icon(fn (string $state) => match ($state) {
                            'created' => Heroicon::OutlinedPlusCircle,
                            'deleted' => Heroicon::OutlinedMinusCircle,
                            'updated' => Heroicon::OutlinedPencilSquare,
                            'restored' => Heroicon::OutlinedArrowUturnLeft,
                            'permissions_modified' => Heroicon::OutlinedShieldCheck,
                            default => Heroicon::OutlinedTag,
                        })
                        ->color(fn (string $state) => match ($state) {
                            'created', 'restored' => 'success',
                            'deleted' => 'danger',
                            'updated', 'permissions_modified' => 'warning',
                            default => 'gray',
                        })
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
                                return new HtmlString('<span class="italic text-gray-400 dark:text-gray-500">System</span>');
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
                            ? UserResource::getUrl('view', ['record' => $record->user])
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
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'restored' => 'Restored',
                        'permissions_modified' => 'Permissions Modified',
                    ])
                    ->native(false)
                    ->searchable()
                    ->preload(),

                Filter::make('created_at_range')
                    ->label('Date Range')
                    ->columnSpan(2)
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
            ->emptyStateHeading('No definition history yet')
            ->emptyStateDescription('Changes to this role\'s name, type, and permissions will appear here.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    /**
     * Generate a human-readable summary of what changed in an audit entry.
     */
    public static function summarizeChanges(Audit $audit): HtmlString
    {
        $html = match ($audit->event) {
            'created' => self::pill('Role created', 'success'),
            'deleted' => self::pill('Role deleted', 'danger'),
            'restored' => self::pill('Role restored', 'success'),
            'updated' => self::summarizeAttributeChanges($audit),
            'permissions_modified' => self::summarizePermissionChanges($audit),
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
            : self::pill('Role updated', 'gray');
    }

    /**
     * Summarize permission changes by diffing old/new permission arrays.
     */
    private static function summarizePermissionChanges(Audit $audit): string
    {
        /** @var list<array{name: string, label: string}> $oldPermissionData */
        $oldPermissionData = $audit->old_values['permissions'] ?? [];
        /** @var list<array{name: string, label: string}> $newPermissionData */
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
            : self::pill('Permissions modified', 'gray');
    }

    /**
     * Render a small pill/badge matching Filament's fi-badge styling.
     */
    private static function pill(string $text, string $color): string
    {
        $colors = match ($color) {
            'success' => 'fi-color-success bg-success-50 text-success-600 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/20',
            'danger' => 'fi-color-danger bg-danger-50 text-danger-600 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/20',
            default => 'fi-color-gray bg-gray-50 text-gray-600 ring-gray-600/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20',
        };

        return '<span class="fi-badge fi-size-sm inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ' . $colors . '">'
            . e($text) . '</span>';
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

        $parts = array_map(fn (string $label) => self::pill($prefix . ' ' . $label, $color), $visible);

        if ($remaining > 0) {
            $overflowLabels = array_slice($labels, $maxVisible);
            $tooltip = e(implode(', ', $overflowLabels));
            $parts[] = '<span title="' . $tooltip . '">'
                . self::pill('+' . $remaining . ' more', 'gray')
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
            'role_type_id' => 'Role type',
            'assignment_locked' => 'Assignment locked',
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
            $roleType = RoleType::find($value);

            return $roleType?->slug?->getLabel() ?? (string) $value;
        }

        if ($key === 'assignment_locked') {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }
}
