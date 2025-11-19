<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Listeners;

use App\Domains\User\Listeners\LogImpersonationAccess;
use App\Domains\User\Models\ImpersonationLog;
use App\Domains\User\Models\User;
use Lab404\Impersonate\Events\TakeImpersonation;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LogImpersonationAccess::class)]
class LogImpersonationAccessTest extends TestCase
{
    public function test_impersonation_log_is_created(): void
    {
        $impersonator = User::factory()->create();
        $impersonated = User::factory()->create();

        $takeImpersonationEvent = new TakeImpersonation($impersonator, $impersonated);

        $impersonateEvent = new LogImpersonationAccess();
        $impersonateEvent->handle($takeImpersonationEvent);

        $this->assertDatabaseHas(ImpersonationLog::class, [
            'impersonator_user_id' => $impersonator->id,
            'impersonated_user_id' => $impersonated->id,
        ]);
    }
}
