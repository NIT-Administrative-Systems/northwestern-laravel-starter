<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Core\Models\Export;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export as BaseExport;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class FilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BaseExport::class, Export::class);
    }

    public function boot(): void
    {
        $timezone = fn () => once(fn () => auth()->user()->timezone ?? config('app.timezone'));

        Table::configureUsing(
            fn (Table $table) => $table
                ->defaultDateTimeDisplayFormat(config('platform.datetime_display_format'))
                ->deferFilters(false)
                ->paginationPageOptions([10, 25, 50, 100])
                ->defaultPaginationPageOption(25)
                // Below sm, each row is a card with a label on every value.
                ->stackedOnMobile()
                // Opening a record and coming back keeps the table as it was left.
                ->persistFiltersInSession()
                ->persistSortInSession()
                ->persistSearchInSession()
                // Filament's default is an x-mark, which reads as an error.
                ->emptyStateIcon(Heroicon::OutlinedInbox)
        );

        Schema::configureUsing(fn (Schema $schema) => $schema
            ->defaultDateTimeDisplayFormat(config('platform.datetime_display_format')));

        Select::configureUsing(fn (Select $component) => $component->native(false));
        SelectFilter::configureUsing(fn (SelectFilter $component) => $component->native(false));

        DateTimePicker::configureUsing(function (DateTimePicker $component) use ($timezone) {
            $component->timezone($timezone());
        });

        // Dates and times show in the person's timezone, including date-only ones such
        // as a timestamp shown with ->date(). A DATE column has no time to convert,
        // so use ->date(timezone: config('app.timezone')) and it keeps its day.
        TextColumn::configureUsing(static function (TextColumn $component) use ($timezone) {
            $component->timezone(function (TextColumn $column) use ($timezone): ?string {
                if ($column->isDateTime() || $column->isDate()) {
                    return $timezone();
                }

                return null;
            });
        });

        TextEntry::configureUsing(static function (TextEntry $component) use ($timezone) {
            $component->timezone(function (TextEntry $column) use ($timezone): ?string {
                if ($column->isDateTime() || $column->isDate()) {
                    return $timezone();
                }

                return null;
            });
        });

        ExportAction::configureUsing(
            fn (ExportAction $action) => $action
                ->label('Export')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->fileDisk('s3')
                ->formats([ExportFormat::Csv])
        );
    }
}
