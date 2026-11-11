<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Actions\Local\FixedNumericOneTimeCodeGenerator;
use App\Domains\Auth\Actions\Local\RandomNumericOneTimeCodeGenerator;
use App\Domains\Auth\Contracts\OneTimeCodeGenerator;
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

        // OAuth and MCP clients expect their protocols' own error bodies.
        $this->app->singleton(ProblemDetailsRenderer::class, fn (): ProblemDetailsRenderer => new ProblemDetailsRenderer(
            exceptPaths: ['oauth/*', 'mcp', 'mcp/*', '.well-known/*'],
        ));
        $this->app->bind(DbDumperFactory::class, fn (): ConfigurableDbDumperFactory => new ConfigurableDbDumperFactory());

        if (config('local-auth.use_fixed_code')) {
            // Fails the deployment at once, rather than on the first sign-in code.
            FixedNumericOneTimeCodeGenerator::ensureAllowed();
        }

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

    protected function configureVite(): void
    {
        Vite::useAggressivePrefetching();
    }

    /**
     * Register the eager-loading user provider, and let Manage All past every gate.
     */
    protected function configureAuthentication(): void
    {
        Auth::provider(
            'eager-load-eloquent',
            /** @phpstan-ignore-next-line  */
            static fn (Application $application, array $config): EagerLoadEloquentUserProvider => new EagerLoadEloquentUserProvider($application['hash'], $config['model'])
        );

        // Test each new check with and without Manage All, which passes every one of them.
        Gate::before(static function (User $user): ?true {
            // An API token keeps to its scopes, a super administrator's included.
            if ($user->currentAccessToken() instanceof \Laravel\Passport\Contracts\ScopeAuthorizable) {
                return null;
            }

            return $user->hasPermissionTo(SystemPermission::ManageAll) ? true : null;
        });
    }

    /**
     * Refuse destructive database commands, such as `migrate:fresh`, in production.
     */
    protected function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(App::isProduction());
    }

    /**
     * Force HTTPS URLs in deployed environments.
     */
    protected function configureRoutes(): void
    {
        // Deployed environments are always HTTPS behind their proxy. Locally,
        // follow APP_URL, so a plain-HTTP server on any port keeps working.
        $localOverHttp = App::isLocal() && ! str_starts_with((string) config('app.url'), 'https://');

        if (! App::environment(['ci', 'testing']) && ! $localOverHttp) {
            URL::forceScheme('https');
        }
    }

    /**
     * Show whole HTTP client error bodies locally; refuse unfaked requests in tests.
     */
    protected function configureRequests(): void
    {
        if (App::environment(['local', 'ci', 'testing'])) {
            RequestException::dontTruncate();
        }

        if (App::environment(['ci', 'testing'])) {
            Http::preventStrayRequests();
        }
    }
}
