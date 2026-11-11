<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal): bool {
            if ($isLocal) {
                return true;
            }

            return $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Keep secrets out of the requests Telescope
     * records outside the local environment.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        // The CSRF token, and the secrets, codes and
        // refresh tokens that OAuth clients send.
        Telescope::hideRequestParameters([
            '_token',
            'client_secret',
            'refresh_token',
            'code',
            'code_verifier',
            'secret',
        ]);

        // X-Secret-Token carries the health endpoint's secret.
        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'authorization',
            'x-secret-token',
        ]);
    }

    /**
     * Register the gate that decides who may open
     * Telescope outside the local environment.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', static fn (?User $user) => $user?->can(SystemPermission::ManageAll));
    }
}
