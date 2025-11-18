<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Admin;

use App\Domains\User\Actions\StartImpersonation;
use App\Domains\User\Actions\StopImpersonation;
use App\Domains\User\Models\User;
use App\Http\Controllers\Admin\ImpersonationController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ImpersonationController::class)]
class ImpersonationControllerTest extends TestCase
{
    public function test_take_impersonation_redirects_to_custom_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->mock(StartImpersonation::class, function ($mock) use ($user) {
            $mock->expects('__invoke')
                ->with($this->equalTo($user), $this->equalTo(2), $this->equalTo('web'))
                ->andReturns('https://example.com/dashboard');
        });

        $response = $this->get('/impersonate/take/2/web');

        $response->assertRedirect('https://example.com/dashboard');
    }

    public function test_take_impersonation_redirects_back(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('back');
        });

        $response = $this->get('/impersonate/take/2/web');

        $response->assertRedirect('/');
    }

    public function test_leave_impersonation_redirects_to_custom_url(): void
    {
        $this->mock(StopImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('https://example.com/dashboard');
        });

        $response = $this->get('/impersonate/leave');

        $response->assertRedirect('https://example.com/dashboard');
    }

    public function test_leave_impersonation_redirects_back(): void
    {
        $this->mock(StopImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('back');
        });

        $response = $this->get('/impersonate/leave');

        $response->assertRedirect();
    }

    public function test_take_impersonation_fails_without_authenticated_user(): void
    {
        auth()->logout();

        $response = $this->get(route('impersonate', 2));

        $response->assertRedirect('/auth/type');

        $this->assertGuest();
    }
}
