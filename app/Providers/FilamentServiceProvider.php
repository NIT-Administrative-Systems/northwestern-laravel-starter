<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\User\Models\Export;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
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
use Northwestern\SysDev\Chassis\Formatting\TitleCase;

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
                ->emptyStateHeading(fn (Table $table): string => __('filament-tables::table.empty.heading', [
                    'model' => TitleCase::of($table->getPluralModelLabel()),
                ]))
        );

        $this->titleCaseBuiltInActions();

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

    /**
     * Filament's built-in actions put the lowercase model label into some of their names ("New
     * :label"), and title-case it with Str::ucwords elsewhere ("Sign-in Record"). Names are title
     * case in Chicago style here (see "Interface copy" in .github/copilot-instructions.md); an
     * action's own ->label() or ->modalHeading() still wins.
     */
    private function titleCaseBuiltInActions(): void
    {
        CreateAction::configureUsing(fn (CreateAction $action) => $action
            ->label(fn (CreateAction $action): string => __('filament-actions::create.single.label', ['label' => TitleCase::of($action->getModelLabel())]))
            ->modalHeading(fn (CreateAction $action): string => __('filament-actions::create.single.modal.heading', ['label' => TitleCase::of($action->getModelLabel())])));

        AttachAction::configureUsing(fn (AttachAction $action) => $action
            ->modalHeading(fn (AttachAction $action): string => __('filament-actions::attach.single.modal.heading', ['label' => TitleCase::of($action->getModelLabel())])));

        DeleteBulkAction::configureUsing(fn (DeleteBulkAction $action) => $action
            ->modalHeading(fn (DeleteBulkAction $action): string => __('filament-actions::delete.multiple.modal.heading', ['label' => TitleCase::of($action->getPluralModelLabel())])));

        ExportAction::configureUsing(fn (ExportAction $action) => $action
            ->label(fn (ExportAction $action): string => __('filament-actions::export.label', ['label' => TitleCase::of($action->getPluralModelLabel())]))
            ->modalHeading(fn (ExportAction $action): string => __('filament-actions::export.modal.heading', ['label' => TitleCase::of($action->getPluralModelLabel())])));
    }
}
