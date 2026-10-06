<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\User\Models\Export;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export as BaseExport;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
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
        );

        Schema::configureUsing(fn (Schema $infolist) => $infolist
            ->defaultDateTimeDisplayFormat(
                (config('platform.datetime_display_format'))
            ));

        Select::configureUsing(fn (Select $component) => $component->native(false));
        SelectFilter::configureUsing(fn (SelectFilter $component) => $component->native(false));

        DateTimePicker::configureUsing(function (DateTimePicker $component) use ($timezone) {
            $component->timezone($timezone());
        });

        TextColumn::configureUsing(static function (TextColumn $component) use ($timezone) {
            $component->timezone(function (TextColumn $column) use ($timezone): ?string {
                if ($column->isDateTime()) {
                    return $timezone();
                }

                return null;
            });
        });

        TextEntry::configureUsing(static function (TextEntry $component) use ($timezone) {
            $component->timezone(function (TextEntry $column) use ($timezone): ?string {
                if ($column->isDateTime()) {
                    return $timezone();
                }

                return null;
            });
        });

        ExportAction::configureUsing(
            fn (ExportAction $action) => $action
                ->fileDisk('s3')
                ->formats([ExportFormat::Csv])
        );
    }
}
