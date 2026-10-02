<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Domains\Core\Exceptions\SentryExceptionHandler;
use App\Domains\User\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

/**
 * Loads the Sentry browser SDK with this application's DSN, routed through the
 * `sentry.tunnel` route so ad blockers don't drop reports. Renders nothing when
 * no DSN is configured.
 *
 * Both panels add it through a render hook; the public layout includes it directly.
 */
class SentryBrowser extends Component
{
    public function shouldRender(): bool
    {
        return filled(config('sentry.dsn'));
    }

    public function render(): View
    {
        return view('components.sentry-browser', [
            'config' => $this->config(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        // Error pages render this when the database may be down, so a failed user lookup means no user context.
        $user = rescue(fn () => auth()->user(), null, report: false);

        return [
            'dsn' => config('sentry.dsn'),
            'environment' => config('app.env'),
            'tunnel' => Route::has('sentry.tunnel') ? route('sentry.tunnel', absolute: false) : null,
            'tracing' => (bool) config('northwestern-theme.sentry-enable-apm-js'),
            'tracesSampleRate' => (float) config('northwestern-theme.sentry-traces-sample-rate'),
            'user' => $user instanceof User ? resolve(SentryExceptionHandler::class)->userContext($user) : null,
        ];
    }
}
