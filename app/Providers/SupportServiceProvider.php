<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Gateway\TicketSystemGatewayFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the support system's service container bindings.
 *
 * When support is disabled ({@see config('support.enabled')}), no bindings
 * are registered and the support system is effectively inert. Projects can
 * override the {@see TicketSystemGateway} binding to use a completely
 * different gateway without modifying the factory or enum.
 */
class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('support.enabled')) {
            return;
        }

        $this->app->bind(TicketSystemGateway::class, function ($app) {
            return $app->make(TicketSystemGatewayFactory::class)->default();
        });
    }

    public function boot(): void
    {
        //
    }
}
