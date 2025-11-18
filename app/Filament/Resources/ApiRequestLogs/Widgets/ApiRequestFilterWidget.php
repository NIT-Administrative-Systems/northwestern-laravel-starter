<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestLogs\Widgets;

use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Models\User;
use Carbon\Carbon;
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
class ApiRequestFilterWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    public const string EVENT_DATE_RANGE_UPDATED = 'apiRequestDateRangeUpdated';

    public const string EVENT_USER_FILTER_UPDATED = 'apiRequestUserFilterUpdated';

    protected int|string|array $columnSpan = 'full';

    public ?string $preset = 'last_30_days';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?int $userId = null;

    protected string $view = 'filament.resources.api-request-logs.widgets.filter-widget';

    public function mount(): void
    {
        $this->applyPreset('last_30_days');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('userId')
                    ->label('User')
                    ->placeholder('All Users')
                    ->options(function () {
                        return User::query()
                            ->where('auth_type', AuthTypeEnum::API)
                            ->orderBy('username')
                            ->pluck('username', 'id');
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->userId = $state;
                        $this->broadcastUserFilter();
                    }),

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
                    ])
                    ->default('last_30_days')
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->preset = $state;
                        $this->applyPreset($state);
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
                    }),
            ])
            ->columns(3);
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

        $this->startDate = $start->utc()->toDateTimeString();
        $this->endDate = $end->utc()->toDateTimeString();

        $this->form->fill([
            'preset' => $preset,
        ]);

        $this->broadcastDateRange();
    }

    protected function broadcastDateRange(): void
    {
        if ($this->startDate && $this->endDate) {
            $this->dispatch(self::EVENT_DATE_RANGE_UPDATED, startDate: $this->startDate, endDate: $this->endDate);
        }
    }

    protected function broadcastUserFilter(): void
    {
        $this->dispatch(self::EVENT_USER_FILTER_UPDATED, userId: $this->userId);
    }
}
