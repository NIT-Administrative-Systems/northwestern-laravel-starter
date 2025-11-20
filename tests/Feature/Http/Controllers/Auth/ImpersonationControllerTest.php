<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Domains\User\Actions\Impersonation\StartImpersonation;
use App\Domains\User\Actions\Impersonation\StopImpersonation;
use App\Domains\User\Models\User;
use App\Http\Controllers\Auth\ImpersonationController;
use Illuminate\Support\Facades\Session;
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

    public function test_take_impersonation_redirects_to_custom_non_root_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('/admin/users');
        });

        $response = $this->get('/impersonate/take/2/web');

        $response->assertRedirect('/admin/users');
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

    public function test_take_impersonation_redirects_to_referer_if_redirect_is_root(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $referer = 'https://my-app.test/current/page';

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('/');
        });

        $response = $this->get('/impersonate/take/2/web', [
            'Referer' => $referer,
        ]);

        $response->assertRedirect($referer);
    }

    public function test_take_impersonation_stores_valid_referer_url_in_session(): void
    {
        Session::start();
        $user = User::factory()->create();
        $this->actingAs($user);

        $referer = 'https://my-app.test/some-page';

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')->andReturns('back');
        });

        $this->get('/impersonate/take/2/web', ['Referer' => $referer]);

        $this->assertSame($referer, session('impersonation.return_url'));
    }
}
