<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions;

use App\Domains\User\Actions\StartImpersonation;
use App\Domains\User\Actions\StopImpersonation;
use App\Domains\User\Enums\RoleTypeEnum;
use App\Domains\User\Models\Role;
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
