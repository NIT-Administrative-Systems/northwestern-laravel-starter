<?php

declare(strict_types=1);

namespace App\Domains\User\Listeners;

use App\Domains\User\Models\ImpersonationLog;
use Lab404\Impersonate\Events\TakeImpersonation;

/**
 * When a {@see TakeImpersonation} event is fired by the {@see \Lab404\Impersonate\Impersonate} package, create a new
 * {@see ImpersonationLog} so that we can track who impersonated who.
 */
class ImpersonateEvent
{
    public function handle(TakeImpersonation $event): void
    {
        ImpersonationLog::create([
            'impersonator_user_id' => $event->impersonator->getAuthIdentifier(),
            'impersonated_user_id' => $event->impersonated->getAuthIdentifier(),
        ]);
    }
}
