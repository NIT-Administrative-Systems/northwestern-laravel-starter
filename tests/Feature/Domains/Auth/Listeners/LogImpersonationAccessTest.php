<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Listeners;

use App\Domains\Auth\Listeners\LogImpersonationAccess;
use App\Domains\Auth\Models\ImpersonationLog;
use App\Domains\User\Models\User;
use Lab404\Impersonate\Events\TakeImpersonation;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LogImpersonationAccess::class)]
final class LogImpersonationAccessTest extends TestCase
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
