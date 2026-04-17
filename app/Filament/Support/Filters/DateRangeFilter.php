<?php

declare(strict_types=1);

namespace App\Filament\Support\Filters;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds consistent Filament date range filters with shared query logic.
 */
class DateRangeFilter
{
    public const string ModeDate = 'date';

    public const string ModeDateTime = 'datetime';

    /**
     * Create a date range filter with optional indicators and icon styling.
     */
    public function make(
        string $name,
        string $label,
        string $column,
        string $fromField = 'from',
        string $untilField = 'to',
        string $mode = self::ModeDateTime,
        ?Heroicon $icon = null,
        bool $limitUntilToToday = false,
        bool $showIndicators = true,
        ?string $fromLabel = null,
        ?string $untilLabel = null,
    ): Filter {
        $fromPicker = DatePicker::make($fromField)
            ->label($fromLabel ?? 'From')
            ->native(false);

        $untilPicker = DatePicker::make($untilField)
            ->label($untilLabel ?? 'To')
            ->native(false)
            ->minDate(fn (callable $get) => $get($fromField));

        if ($icon instanceof Heroicon) {
            $fromPicker->prefixIcon($icon)->closeOnDateSelection();
            $untilPicker->prefixIcon($icon)->closeOnDateSelection();
        }

        if ($limitUntilToToday) {
            $untilPicker->maxDate(Carbon::today());
        }

        $filter = Filter::make($name)
            ->label($label)
            ->columns(2)
            ->schema([$fromPicker, $untilPicker])
            ->query(fn (Builder $query, array $data): Builder => $this->apply($query, $data, $column, $fromField, $untilField, $mode));

        if ($showIndicators) {
            $filter->indicateUsing(fn (array $data): array => $this->indicators($data, $fromField, $untilField));
        }

        return $filter;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * Apply a from / until date range to an Eloquent query.
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<TModel>
     */
    public function apply(
        Builder $query,
        array $data,
        string $column,
        string $fromField = 'from',
        string $untilField = 'to',
        string $mode = self::ModeDateTime,
    ): Builder {
        $from = $data[$fromField] ?? null;
        $until = $data[$untilField] ?? null;

        if ($mode === self::ModeDate) {
            if ($from !== null && $from !== '') {
                $query->whereDate($column, '>=', $from);
            }

            if ($until !== null && $until !== '') {
                $query->whereDate($column, '<=', $until);
            }

            return $query;
        }

        if (filled($from)) {
            $query->where($column, '>=', Carbon::parse((string) $from)->startOfDay());
        }

        if (filled($until)) {
            $query->where($column, '<=', Carbon::parse((string) $until)->endOfDay());
        }

        return $query;
    }

    /**
     * Build indicator labels for the active date range values.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function indicators(array $data, string $fromField = 'from', string $untilField = 'to'): array
    {
        $indicators = [];

        if (filled($data[$fromField] ?? null)) {
            $indicators[] = 'From: ' . Carbon::parse((string) $data[$fromField])->toDateString();
        }

        if (filled($data[$untilField] ?? null)) {
            $indicators[] = 'To: ' . Carbon::parse((string) $data[$untilField])->toDateString();
        }

        return $indicators;
    }
}
