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
enum TicketSystemEnum: string implements HasLabel
{
    case TEAM_DYNAMIX = 'team-dynamix';
    case MAIL = 'mail';

    public function getLabel(): string
    {
        return match ($this) {
            self::TEAM_DYNAMIX => 'TeamDynamix',
            self::MAIL => 'Email',
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
            self::TEAM_DYNAMIX => TeamDynamixGateway::class,
            self::MAIL => MailGateway::class,
        };
    }
}
