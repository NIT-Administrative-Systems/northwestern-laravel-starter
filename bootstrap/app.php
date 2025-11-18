<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\ProblemDetailsRenderer;
use App\Domains\Core\Exceptions\SentryExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
        $middleware->redirectGuestsTo(fn () => route('login-selection'));
        $middleware->redirectUsersTo('/');

        $middleware->validateCsrfTokens(except: [
            '/__cypress__/artisan',
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Database timeout errors (custom handling for web)
        $exceptions->render(function (PDOException $e): ?Response {
            if (str_contains($e->getMessage(), 'timeout expired')) {
                return response()->view('errors.database-paused', [], 500);
            }

            return null;
        });

        // Skip reporting database timeout noise in non-production environments - these are common when RDS is waking up
        $exceptions->report(function (PDOException $e): bool {
            return ! (! app()->environment('production') && str_contains($e->getMessage(), 'timeout expired'));
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
