<?php

declare(strict_types=1);

namespace App\Domains\Core\Seeders\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use OwenIt\Auditing\AuditableObserver;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Enables audit logging for seeders that run in deployed environments.
 *
 * By default, Laravel Auditing disables auditing for console commands.
 * This concern directly registers the AuditableObserver on the specified
 * models without toggling the global `audit.console` config. This avoids
 * accidentally enabling auditing on unrelated models that happen to boot
 * while the config is enabled.
 *
 * Auditing is skipped in testing environments to avoid unnecessary
 * overhead and test complexity.
 *
 * Usage:
 * <code>
 *   use AuditsSeederChanges;
 *
 *   public function run(): void
 *   {
 *       $this->withAuditing([Role::class], function () {
 *           // Seeder logic...
 *       });
 *   }
 * </code>
 */
trait AuditsSeederChanges
{
    /**
     * Models that have already had the AuditableObserver registered
     * during this process. Prevents duplicate observers when multiple
     * seeders audit the same model or when `withAuditing` is called
     * more than once.
     *
     * @var array<class-string<Model&Auditable>, true>
     */
    private static array $observedModels = [];

    /**
     * Run a callback with auditing enabled for the given models.
     *
     * Registers the AuditableObserver directly on each model class
     * rather than toggling the global `audit.console` config. This
     * prevents other Auditable models from inadvertently gaining
     * observers if they happen to boot during the callback.
     *
     * @template TReturn
     *
     * @param  list<class-string<Model&Auditable>>  $models
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    protected function withAuditing(array $models, callable $callback): mixed
    {
        foreach ($models as $model) {
            $this->ensureAuditable($model);
        }

        if (App::environment(['testing', 'ci'])) {
            return $callback();
        }

        foreach ($models as $model) {
            $this->registerObserverOnce($model);
        }

        return $callback();
    }

    /**
     * Register the AuditableObserver on a model class, skipping if
     * already registered during this process.
     *
     * @param  class-string<Model&Auditable>  $model
     */
    private function registerObserverOnce(string $model): void
    {
        if (isset(self::$observedModels[$model])) {
            return;
        }

        $model::observe(new AuditableObserver());
        self::$observedModels[$model] = true;
    }

    /**
     * Validate that a model class implements the Auditable contract.
     *
     * @param  class-string<Model>  $model
     *
     * @throws InvalidArgumentException
     */
    private function ensureAuditable(string $model): void
    {
        if (! is_a($model, Auditable::class, true)) {
            throw new InvalidArgumentException(
                "{$model} must implement " . Auditable::class . ' to be used with withAuditing().'
            );
        }
    }
}
