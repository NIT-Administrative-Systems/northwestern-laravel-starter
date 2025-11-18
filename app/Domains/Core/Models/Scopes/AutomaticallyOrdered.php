<?php

declare(strict_types=1);

namespace App\Domains\Core\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Scope that orders by the `sort_index`, and then the `label`.
 *
 * This is a convenience trait. If you are using something other than label, you can just define the scope in your model.
 */
class AutomaticallyOrdered implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder
            ->orderBy('sort_index')
            ->orderBy('label');
    }
}
