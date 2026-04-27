<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Auth\Actions\Local\FixedNumericOneTimeCodeGenerator;
use App\Domains\Auth\Actions\Local\RandomNumericOneTimeCodeGenerator;
use App\Domains\Auth\Contracts\OneTimeCodeGenerator;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\RequestException;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Northwestern\SysDev\Chassis\Database\ConfigurableDbDumperFactory;
use Northwestern\SysDev\Chassis\Exceptions\ProblemDetailsRenderer;
use Northwestern\SysDev\UI\Providers\NorthwesternUiServiceProvider;
use Spatie\DbSnapshots\DbDumperFactory;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Authenticatable::class, User::class);
        $this->app->singleton(ProblemDetailsRenderer::class);
        $this->app->bind(DbDumperFactory::class, function (): ConfigurableDbDumperFactory {
            return new ConfigurableDbDumperFactory();
        });
        $this->app->singleton(
            OneTimeCodeGenerator::class,
            config('local-auth.use_fixed_code')
                ? FixedNumericOneTimeCodeGenerator::class
                : RandomNumericOneTimeCodeGenerator::class,
        );

        Paginator::useBootstrapFive();
    }

    public function boot(): void
    {
        $this->configureVite();
        $this->configureAuthentication();
        $this->configureCommands();
        $this->configureRoutes();
        $this->configureRequests();
        $this->configureSentry();
    }

    /** Configure Vite asset handling and prefetching strategy. */
    protected function configureVite(): void
    {
        Vite::useAggressivePrefetching();
    }

    /** Register the custom user provider and configure the super-admin gate bypass. */
    public function configureAuthentication(): void
    {
        Auth::provider('eager-load-eloquent', static function (Application $application, array $config): EagerLoadEloquentUserProvider {
            /** @phpstan-ignore-next-line  */
            return new EagerLoadEloquentUserProvider($application['hash'], $config['model']);
        });

        /**
         * Users with the {@see SystemPermission::ManageAll} permission bypass all authorization checks.
         * This is important to remember when adding new authorization checks to the application.
         * Be sure to accurately test new features with and without the permission.
         */
        Gate::before(static function (User $user): ?true {
            return $user->hasPermissionTo(SystemPermission::ManageAll) ? true : null;
        });
    }

    /** Prevent destructive database commands (migrate:fresh, db:wipe, etc.) in production. */
    public function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(App::isProduction());
    }

    /** Force HTTPS in deployed environments. */
    public function configureRoutes(): void
    {
        if (! App::environment(['ci', 'testing'])) {
            URL::forceScheme('https');
        }
    }

    /** Show full HTTP exception bodies locally and prevent unmocked HTTP calls in tests. */
    public function configureRequests(): void
    {
        if (App::environment(['local', 'ci', 'testing'])) {
            RequestException::dontTruncate();
        }

        if (App::environment(['ci', 'testing'])) {
            Http::preventStrayRequests();
        }
    }

    /**
     * Registers user context for the browser Sentry SDK. The northwestern-laravel-ui
     * Blade template calls `Sentry.setUser()` with the object on every page load,
     * so JS errors carry user identity. PHP-side context is handled separately by
     * {@see \App\Domains\Core\Exceptions\SentryExceptionHandler}.
     */
    public function configureSentry(): void
    {
        NorthwesternUiServiceProvider::setSentryUserContext(static function (?User $user) {
            if (! $user instanceof User) {
                return null;
            }

            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'primary_affiliation' => $user->primary_affiliation,
                'auth_type' => $user->auth_type,
            ];
        });
    }
}
