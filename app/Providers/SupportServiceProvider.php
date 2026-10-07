<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Chooses the ticket system support requests go to: the {@see TicketSystemGateway} for the
 * `support.driver` config value, as {@see TicketSystem::gatewayClass()} maps it.
 *
 * When support is disabled ({@see config('support.enabled')}), nothing is bound and the support
 * system is inert. An application can bind its own {@see TicketSystemGateway} in a provider's
 * `boot()` to send requests somewhere else.
 */
class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('support.enabled')) {
            return;
        }

        $this->app->bind(TicketSystemGateway::class, function (Application $app): TicketSystemGateway {
            $driver = config('support.driver');
            $system = (is_string($driver) ? TicketSystem::tryFrom($driver) : null) ?? throw new InvalidArgumentException(sprintf(
                'Unsupported support driver: [%s]. Supported: %s.',
                is_scalar($driver) ? $driver : get_debug_type($driver),
                implode(', ', array_column(TicketSystem::cases(), 'value')),
            ));

            return $app->make($system->gatewayClass());
        });
    }
}
