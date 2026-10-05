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
    }

    public function boot(): void
    {
        $this->configureVite();
        $this->configureAuthentication();
        $this->configureCommands();
        $this->configureRoutes();
        $this->configureRequests();
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
         *
         * Not on API requests: a token is limited to its scopes, and its user's own permissions
         * and policies decide the rest, so a super-administrator's token is no exception.
         */
        Gate::before(static function (User $user): ?true {
            if ($user->currentAccessToken() instanceof \Laravel\Passport\Contracts\ScopeAuthorizable) {
                return null;
            }

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
        // Deployed environments are always HTTPS behind their proxy. Locally, follow APP_URL, so a
        // plain-HTTP server on any port (a worktree's, an agent's) keeps working.
        $localOverHttp = App::isLocal() && ! str_starts_with((string) config('app.url'), 'https://');

        if (! App::environment(['ci', 'testing']) && ! $localOverHttp) {
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
}
