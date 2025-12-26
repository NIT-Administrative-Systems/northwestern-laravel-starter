<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Domains\Core\Database\ConfigurableDbDumperFactory;
use App\Domains\Core\Exceptions\ProblemDetailsRenderer;
use App\Domains\User\Models\User;
use App\Http\Responses\ProblemDetails;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\DbSnapshots\DbDumperFactory;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Paginator::useBootstrapFive();

        $this->app->singleton(ProblemDetailsRenderer::class);
        $this->app->bind(DbDumperFactory::class, function (): ConfigurableDbDumperFactory {
            return new ConfigurableDbDumperFactory();
        });
    }

    public function boot(): void
    {
        $this->configureAuthentication();
        $this->configureCommands();
        $this->configureRoutes();
        $this->configureExceptions();
    }

    public function configureAuthentication(): void
    {
        Auth::provider('eager-load-eloquent', static function (Application $application, array $config): EagerLoadEloquentUserProvider {
            /** @phpstan-ignore-next-line  */
            return new EagerLoadEloquentUserProvider($application['hash'], $config['model']);
        });

        /**
         * Users with the {@see PermissionEnum::MANAGE_ALL} permission bypass all authorization checks.
         * This is important to remember when adding new authorization checks to the application.
         * Be sure to accurately test new features with and without the permission.
         */
        Gate::before(static function (User $user): ?true {
            return $user->hasPermissionTo(PermissionEnum::MANAGE_ALL) ? true : null;
        });
    }

    public function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(App::isProduction());
    }

    public function configureRoutes(): void
    {
        if (! App::environment(['ci', 'testing'])) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', static function (Request $request) {
            return Limit::perMinute((int) config('auth.api.rate_limit.max_attempts'))
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn () => ProblemDetails::tooManyRequests());
        });

        RateLimiter::for('login-code-request', static function (Request $request) {
            $email = mb_strtolower(
                (string) $request->input(
                    key: 'email',
                    default: $request->session()->get(LoginCodeSession::EMAIL, '')
                )
            );

            return [
                Limit::perMinute(5)->by($request->ip() ?? 'ip:none'),
                Limit::perMinute(3)->by('login-code:req:' . $email),
            ];
        });

        RateLimiter::for('login-code-verify', static function (Request $request) {
            $ip = $request->ip() ?? 'ip:none';
            $encryptedChallengeId = $request->session()->get(LoginCodeSession::CHALLENGE_ID);

            $challengeId = 'no-challenge';
            if ($encryptedChallengeId) {
                try {
                    $challengeId = Crypt::decryptString($encryptedChallengeId);
                } catch (DecryptException) {
                    $challengeId = 'invalid:' . substr(md5($encryptedChallengeId), 0, 16);
                }
            }

            return [
                Limit::perMinute(10)->by('login-code:verify:' . $ip),
                Limit::perMinute(5)->by('login-code:verify:challenge:' . $challengeId),
            ];
        });
    }

    public function configureExceptions(): void
    {
        if (App::environment(['local', 'ci', 'testing'])) {
            RequestException::dontTruncate();
        }
    }
}
