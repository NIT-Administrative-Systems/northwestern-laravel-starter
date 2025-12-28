<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Impersonation;

use App\Domains\Auth\Actions\Impersonation\StartImpersonation;
use App\Domains\Auth\Actions\Impersonation\StopImpersonation;
use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Event;
use Lab404\Impersonate\Events\LeaveImpersonation;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(StopImpersonation::class)]
class StopImpersonationTest extends TestCase
{
    public function test_stop_impersonation(): void
    {
        Event::fake();

        $staffUser = User::factory()->staff()->createOne();
        $staffUser->roles()->attach(Role::whereHas('role_type', fn ($query) => $query->where('slug', RoleTypeEnum::SYSTEM_MANAGED))->firstOrFail());

        $studentUser = User::factory()->student()->createOne();

        // Start impersonating
        $startImpersonation = new StartImpersonation(app('impersonate'));
        $startImpersonation($staffUser, $studentUser->id);

        // Give it a session so it can pass the path check
        $this->withSession([])->get('/');

        $stopImpersonation = new StopImpersonation(app('impersonate'));
        $redirectTo = $stopImpersonation();

        $this->assertEquals('/', $redirectTo);
        $this->assertFalse($staffUser->isImpersonated());

        Event::assertDispatched(LeaveImpersonation::class);
    }
}
