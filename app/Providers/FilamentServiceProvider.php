<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;

class FilamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_START,
            static fn () => view('components.filament.impersonation-banner')
        );

        $timezone = fn () => once(fn () => auth()->user()->timezone ?? config('app.timezone'));

        Table::configureUsing(
            fn (Table $table) => $table
                ->defaultDateTimeDisplayFormat(config('app.datetime_display_format'))
                ->deferFilters(false)
                ->paginationPageOptions([10, 25, 50, 100])
                ->defaultPaginationPageOption(25)
        );

        Schema::configureUsing(fn (Schema $infolist) => $infolist
            ->defaultDateTimeDisplayFormat(
                (config('app.datetime_display_format'))
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
    }
}
