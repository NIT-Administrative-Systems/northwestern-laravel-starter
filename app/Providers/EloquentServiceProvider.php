<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class EloquentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Identify N+1 queries earlier in the development process.
        Model::preventLazyLoading(! app()->isProduction());

        Model::preventAccessingMissingAttributes(! app()->isProduction());

        /*
        * Ideally, lazy loading violations should be identified and fixed during the development and testing
        * process. In rare cases, however, they may slip through the cracks. For those instances, we don't
        * want to present exceptions to users in non-local environments. Instead, we'll report them
        * to the exception handler.
        */
        if (! app()->hasDebugModeEnabled()) {
            Model::handleLazyLoadingViolationUsing(static function (Model $model, string $relation) {
                $class = $model::class;

                report("Attempted to lazy load [{$relation}] on model [{$class}].");
            });
        }

        /*
        * Guarded / fillable params are awkward to work with.
        *
        * Use Laravel's validator in requests & it'll only give you known & validated fields from POSTs/etc.
        * That solves the problem $fillable wanted to solve, without creating a dozen new problems.
        */
        Model::unguard();

        /**
         * Instead of saving a fully-qualified classpath in morph_type columns (which makes moving/renaming models
         * into a big pain), we set aliases for a friendly string. That way, details about the codebase aren't
         * leaking into the DB.
         */
        Relation::morphMap(array_merge(
            /**
             * Typically, we'd use the following pattern to define morph maps:
             * - Create an abstract class to define the morph map for a specific model type
             * - Define the morph map as a `MORPH_TYPE_MAP` constant in the abstract class
             *     - Model::MORPH_TYPE => Model::class,
             * - Include the morph map here
             */
        ));

        /**
         * The `type` in the {@see Relation::whereHasMorph()} callback does not support aliases and instead
         * returns the fully-qualified classpath of the model. This macro allows us to get the matching
         * alias for a given classpath.
         *
         * {@link https://github.com/laravel/framework/issues/29181}
         */
        Relation::macro('getModelFromAlias', function ($value): string {
            $flippedMorphMap = array_flip(Relation::morphMap());

            return $flippedMorphMap[$value] ?? throw new RuntimeException("No morph alias found for {$value}");
        });
    }
}
