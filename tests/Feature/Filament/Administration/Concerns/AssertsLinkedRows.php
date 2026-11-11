<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Administration\Concerns;

use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;

/**
 * A table whose rows link somewhere wraps every cell in the
 * link, so a cell left empty becomes a link with no text.
 *
 * Columns hidden by default count, since anyone can
 * show them. Columns the table leaves out don't.
 */
trait AssertsLinkedRows
{
    /**
     * @param  Testable<covariant Component&HasTable>  $component
     */
    private function assertEveryEmptyCellHasPlaceholder(Testable $component, Model $record): void
    {
        $table = $component->instance();
        $records = $table->getTableRecords();

        /** @var list<Model> $rows */
        $rows = $records instanceof Collection ? $records->all() : $records->items();
        $loaded = collect($rows)->first(fn (Model $row): bool => $row->is($record));

        $this->assertInstanceOf(Model::class, $loaded, 'The table did not list the record.');

        $emptyCells = [];

        foreach ($table->getTable()->getColumns() as $name => $column) {
            $column->record($loaded);

            if (! $column->isHidden() && blank($column->getState()) && blank($column->getPlaceholder())) {
                $emptyCells[] = $name;
            }
        }

        $this->assertSame([], $emptyCells, 'These columns leave an empty cell without a placeholder.');
    }
}
