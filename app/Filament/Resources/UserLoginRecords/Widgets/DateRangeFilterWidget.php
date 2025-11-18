<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserLoginRecords\Widgets;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * @property-read Schema $form
 */
class DateRangeFilterWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    public const string EVENT_DATE_RANGE_UPDATED = 'dateRangeUpdated';

    protected int|string|array $columnSpan = 'full';

    public ?string $preset = 'last_30_days';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $formStartDate = null;

    public ?string $formEndDate = null;

    protected string $view = 'filament.resources.user-login-records.widgets.date-range-filter-widget';

    public function mount(): void
    {
        $this->applyPreset('last_30_days');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('preset')
                    ->label('Date Range')
                    ->options([
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'last_7_days' => 'Last 7 Days',
                        'last_30_days' => 'Last 30 Days',
                        'last_90_days' => 'Last 90 Days',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                        'this_year' => 'This Year',
                        'custom' => 'Custom Range',
                    ])
                    ->default('last_30_days')
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->preset = $state;
                        if ($state !== 'custom') {
                            $this->applyPreset($state);
                        }
                    }),

                TextEntry::make('date_range_display')
                    ->label('Period')
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray')
                    ->size(TextSize::Large)
                    ->weight(FontWeight::Medium)
                    ->color('gray')
                    ->state(function () {
                        if (! $this->startDate || ! $this->endDate) {
                            return '';
                        }

                        $start = Carbon::parse($this->startDate, 'UTC')->setTimezone(auth()->user()->timezone);
                        $end = Carbon::parse($this->endDate, 'UTC')->setTimezone(auth()->user()->timezone);

                        if ($start->isSameDay($end)) {
                            return $start->format('M j, Y');
                        }

                        return $start->format('M j, Y') . ' - ' . $end->format('M j, Y');
                    })
                    ->visible(fn () => $this->preset !== 'custom'),

                DatePicker::make('formStartDate')
                    ->label('Start Date')
                    ->prefixIcon(Heroicon::Calendar)
                    ->visible(fn () => $this->preset === 'custom')
                    ->closeOnDateSelection()
                    ->live()
                    ->maxDate(fn () => $this->formEndDate ?: Carbon::today())
                    ->afterStateUpdated(fn ($state) => $this->handleStartDateChange($state)),

                DatePicker::make('formEndDate')
                    ->label('End Date')
                    ->prefixIcon(Heroicon::Calendar)
                    ->visible(fn () => $this->preset === 'custom')
                    ->closeOnDateSelection()
                    ->live()
                    ->minDate(fn () => $this->formStartDate)
                    ->maxDate(Carbon::today())
                    ->afterStateUpdated(fn ($state) => $this->handleEndDateChange($state)),
            ])
            ->columns(4);
    }

    protected function applyPreset(?string $preset): void
    {
        if (! $preset) {
            $preset = 'last_30_days';
        }

        $now = Carbon::now(auth()->user()->timezone);

        [$start, $end] = match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_90_days' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };

        // Convert to UTC for database queries
        $this->startDate = $start->utc()->toDateTimeString();
        $this->endDate = $end->utc()->toDateTimeString();

        $this->form->fill([
            'preset' => $preset,
        ]);

        $this->broadcastDateRange();
    }

    public function handleStartDateChange($state): void
    {
        if ($state) {
            $date = Carbon::parse($state, auth()->user()->timezone)->startOfDay();
            $this->startDate = $date->utc()->toDateTimeString();
            $this->formStartDate = $state;
            $this->preset = 'custom';
            $this->checkAndBroadcast();
        }
    }

    public function handleEndDateChange($state): void
    {
        if ($state) {
            $date = Carbon::parse($state, auth()->user()->timezone)->endOfDay();
            $this->endDate = $date->utc()->toDateTimeString();
            $this->formEndDate = $state;
            $this->preset = 'custom';
            $this->checkAndBroadcast();
        }
    }

    protected function checkAndBroadcast(): void
    {
        if ($this->startDate && $this->endDate) {
            $this->broadcastDateRange();
        }
    }

    protected function broadcastDateRange(): void
    {
        if ($this->startDate && $this->endDate) {
            $this->dispatch(self::EVENT_DATE_RANGE_UPDATED, startDate: $this->startDate, endDate: $this->endDate);
        }
    }
}
