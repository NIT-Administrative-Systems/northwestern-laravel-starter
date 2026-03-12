<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

use App\Domains\Support\Contracts\TicketSystemGateway;
use App\Domains\Support\Gateways\Mail\MailGateway;
use App\Domains\Support\Gateways\TeamDynamix\TeamDynamixGateway;
use Filament\Support\Contracts\HasLabel;

/**
 * The available ticket system backends.
 *
 * Each case maps to a concrete {@see TicketSystemGateway} implementation
 * via {@see self::gatewayClass()}. The string value corresponds to the
 * `SUPPORT_DRIVER` env variable and the `ticketing_system` column.
 */
enum TicketSystem: string implements HasLabel
{
    case TeamDynamix = 'team-dynamix';
    case Mail = 'mail';

    public function getLabel(): string
    {
        return match ($this) {
            self::TeamDynamix => 'TeamDynamix',
            self::Mail => 'Email',
        };
    }

    /**
     * The gateway class responsible for this ticket system.
     *
     * @return class-string<TicketSystemGateway>
     */
    public function gatewayClass(): string
    {
        return match ($this) {
            self::TeamDynamix => TeamDynamixGateway::class,
            self::Mail => MailGateway::class,
        };
    }
}
