<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateway;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Enums\TicketSystemEnum;
use App\Domains\Support\Gateways\Mail\MailGateway;
use InvalidArgumentException;

/**
 * Resolves {@see TicketSystemGateway} implementations from the service container.
 *
 * The default gateway is determined by the `support.driver` config value, mapped
 * to a concrete class via {@see TicketSystemEnum::gatewayClass()}. The factory
 * also provides a mail fallback for use when non-mail primary gateways fail.
 */
class TicketSystemGatewayFactory
{
    /**
     * Resolve the configured default gateway.
     */
    public function default(): TicketSystemGateway
    {
        $driver = config('support.driver');

        return $this->make(
            TicketSystemEnum::tryFrom($driver)
                ?? throw new InvalidArgumentException(
                    sprintf(
                        'Unsupported support driver: [%s]. Supported: %s.',
                        $driver,
                        implode(', ', array_column(TicketSystemEnum::cases(), 'value')),
                    )
                )
        );
    }

    /**
     * Resolve the mail fallback gateway (used when primary fails).
     */
    public function fallback(): MailGateway
    {
        return resolve(MailGateway::class, ['isFallbackStrategy' => true]);
    }

    /**
     * Resolve a gateway instance for the given ticket system type.
     *
     * The concrete class is determined by {@see TicketSystemEnum::gatewayClass()},
     * keeping the factory decoupled from individual gateway implementations.
     */
    public function make(TicketSystemEnum $type): TicketSystemGateway
    {
        return resolve($type->gatewayClass());
    }
}
