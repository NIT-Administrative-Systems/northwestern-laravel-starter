<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use App\Domains\User\Events\NetIdUpdated;

/**
 * Types of NetID update actions sent from {@see NetIdUpdated} messages.
 */
enum NetIdUpdateActionEnum: string
{
    case DEACTIVATE = 'deactivate';
    case DEPROVISION = 'deprovision';
    case SECURITY_HOLD = 'sechold';
}
