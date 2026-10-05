<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\SentryExceptionHandler;
use App\Http\Middleware\EnvironmentLockdown;
use App\Http\Middleware\HandleCors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors as FrameworkHandleCors;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Passport\Exceptions\InvalidAuthTokenException;
use Northwestern\SysDev\Chassis\Database\DatabasePausedDetector;
use Northwestern\SysDev\Chassis\Exceptions\ProblemDetailsRenderer;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__ . '/../routes/auth.php',
            __DIR__ . '/../routes/web.php',
        ],
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('filament.app.auth.login'));
        $middleware->redirectUsersTo('/');

        $middleware->validateCsrfTokens(except: [
            '/__cypress__/artisan',
        ]);

        $middleware->web([
            EnvironmentLockdown::class,
        ]);

        $middleware->throttleApi();

        // Adds cors.open_paths, for the MCP server and the OAuth endpoints its clients call.
        $middleware->replace(FrameworkHandleCors::class, HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Database pause errors (custom handling for web)
        $exceptions->render(function (Throwable $e, Request $request): ?Response {
            if (resolve(DatabasePausedDetector::class)->causedByPausedDatabase($e)) {
                return response()->view('errors.database-paused', [], 500);
            }

            return null;
        });

        // An OAuth consent screen approved or denied after its session expired, or in another tab:
        // the request is gone, so there is no application to return to.
        // Mapped before Laravel turns authorization exceptions into a 403.
        $exceptions->map(InvalidAuthTokenException::class, fn (InvalidAuthTokenException $e): HttpException => new HttpException(419, 'This authorization request has expired.', $e));

        // Skip reporting database timeout noise in non-production environments - these are common when RDS is waking up
        $exceptions->report(function (Throwable $e): bool {
            if (app()->environment('production')) {
                return true;
            }

            return ! resolve(DatabasePausedDetector::class)->causedByPausedDatabase($e);
        });

        $exceptions->reportable(function (Throwable $e) {
            resolve(SentryExceptionHandler::class)->report($e);
        });

        // RFC 9457 Problem Details for API routes
        $exceptions->renderable(function (Throwable $e, Request $request) {
            return app(ProblemDetailsRenderer::class)->render($e, $request);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->wantsJson()
        );
    })
    ->withEvents(discover: [
        __DIR__ . '/../app/Domains/*/Listeners',
    ])
    ->create();
