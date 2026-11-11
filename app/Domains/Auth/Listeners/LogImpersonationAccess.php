<?php

declare(strict_types=1);

namespace App\Domains\Auth\Listeners;

use App\Domains\Auth\Models\ImpersonationLog;
use Lab404\Impersonate\Events\TakeImpersonation;

/**
 * Records who impersonated whom when lab404's
 * package fires {@see TakeImpersonation}.
 */
class LogImpersonationAccess
{
    public function handle(TakeImpersonation $event): void
    {
        ImpersonationLog::create([
            'impersonator_user_id' => $event->impersonator->getAuthIdentifier(),
            'impersonated_user_id' => $event->impersonated->getAuthIdentifier(),
        ]);
    }
}
