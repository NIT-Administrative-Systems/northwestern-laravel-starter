<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Actions\Impersonation\StartImpersonation;
use App\Domains\Auth\Actions\Impersonation\StopImpersonation;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Http\Controllers\ImpersonationController;
use App\Domains\User\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ImpersonationController::class)]
class ImpersonationControllerTest extends TestCase
{
    private User $authorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo(SystemPermission::ManageImpersonation);
    }

    public function test_take_impersonation_requires_authorization(): void
    {
        $unauthorizedUser = User::factory()->create();
        $this->actingAs($unauthorizedUser);

        $response = $this->post('/impersonate/take/2/web');

        $response->assertForbidden();
    }

    public function test_take_impersonation_redirects_to_custom_url(): void
    {
        $this->actingAs($this->authorizedUser);

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->with($this->equalTo($this->authorizedUser), $this->equalTo(2), $this->equalTo('web'))
                ->andReturns('https://example.com/dashboard');
        });

        $response = $this->post('/impersonate/take/2/web');

        $response->assertRedirect('https://example.com/dashboard');
    }

    public function test_take_impersonation_redirects_to_custom_non_root_url(): void
    {
        $this->actingAs($this->authorizedUser);

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('/admin/users');
        });

        $response = $this->post('/impersonate/take/2/web');

        $response->assertRedirect('/admin/users');
    }

    public function test_take_impersonation_redirects_back(): void
    {
        $this->actingAs($this->authorizedUser);

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('back');
        });

        $response = $this->post('/impersonate/take/2/web');

        $response->assertRedirect('/');
    }

    public function test_leave_impersonation_redirects_to_custom_url(): void
    {
        $this->mock(StopImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('https://example.com/dashboard');
        });

        $response = $this->post('/impersonate/leave');

        $response->assertRedirect('https://example.com/dashboard');
    }

    public function test_leave_impersonation_redirects_back(): void
    {
        $this->mock(StopImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('back');
        });

        $response = $this->post('/impersonate/leave');

        $response->assertRedirect();
    }

    public function test_take_impersonation_fails_without_authenticated_user(): void
    {
        auth()->logout();

        $response = $this->post(route('impersonate', 2));

        $response->assertRedirect('/auth/type');

        $this->assertGuest();
    }

    public function test_take_impersonation_redirects_to_referer_if_redirect_is_root(): void
    {
        $this->actingAs($this->authorizedUser);

        $referer = config('app.url') . '/current/page';

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('/');
        });

        $response = $this->post('/impersonate/take/2/web', [], [
            'Referer' => $referer,
        ]);

        $response->assertRedirect($referer);
    }

    public function test_take_impersonation_stores_valid_referer_url_in_session(): void
    {
        Session::start();
        $this->actingAs($this->authorizedUser);

        $referer = config('app.url') . '/some/page';

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')->andReturns('back');
        });

        $this->post('/impersonate/take/2/web', [], ['Referer' => $referer]);

        $this->assertSame($referer, session('impersonation.return_url'));
    }

    public function test_take_impersonation_does_not_store_invalid_referer_url_in_session(): void
    {
        Session::start();
        $this->actingAs($this->authorizedUser);

        $referer = 'https://malicious-site.test/some/page';

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')->andReturns('back');
        });

        $this->post('/impersonate/take/2/web', [], ['Referer' => $referer]);

        $this->assertNull(session('impersonation.return_url'));
    }
}
