<?php

declare(strict_types=1);

namespace App\Domains\Core\Models\Scopes;

use App\Domains\Core\Attributes\AutomaticallyOrdered;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

/**
 * Scope that applies automatic ordering based on configurable columns.
 *
 * This scope can be automatically registered with the {@see AutomaticallyOrdered} attribute.
 * It orders by a primary column, and then a secondary column.
 */
readonly class AutomaticallyOrderedScope implements Scope
{
    /**
     * @param  string  $primary  The primary column to order by
     * @param  'asc'|'desc'  $primaryDirection  Sort direction for primary column
     * @param  string  $secondary  The secondary column to order by
     * @param  'asc'|'desc'  $secondaryDirection  Sort direction for secondary column
     */
    public function __construct(
        private string $primary = 'order_index',
        private string $primaryDirection = 'asc',
        private string $secondary = 'label',
        private string $secondaryDirection = 'asc',
    ) {
        //
    }

    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $table = $model->getTable();

        if (Schema::hasColumn($table, $this->primary)) {
            $builder->orderBy($this->primary, $this->primaryDirection);
        }

        if (Schema::hasColumn($table, $this->secondary)) {
            $builder->orderBy($this->secondary, $this->secondaryDirection);
        }
    }
}
