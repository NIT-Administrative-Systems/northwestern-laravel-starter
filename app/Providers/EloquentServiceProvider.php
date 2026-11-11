<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class EloquentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::preventLazyLoading(! App::isProduction());
        Model::preventAccessingMissingAttributes(! App::isProduction());

        // Without debug mode, a lazy load that got past development
        // and tests is reported instead of failing the request.
        if (! App::hasDebugModeEnabled()) {
            Model::handleLazyLoadingViolationUsing(static function (Model $model, string $relation) {
                $class = $model::class;

                report("Attempted to lazy load [{$relation}] on model [{$class}].");
            });
        }

        // Mass assignment stays open. Validate input with
        // a form request or the validator, which pass
        // on only the fields they know, rather than
        // list fillable columns on every model.
        Model::unguard();

        // Morph type columns store an alias rather than a class name, so a model can move
        // or be renamed without breaking its rows. Merge each model's aliases in here.
        Relation::morphMap(array_merge(
            //
        ));
    }
}
