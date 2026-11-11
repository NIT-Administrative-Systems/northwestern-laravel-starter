<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Actions\Impersonation\StartImpersonation;
use App\Domains\Auth\Actions\Impersonation\StopImpersonation;
use App\Domains\Auth\Http\Controllers\ImpersonationController;
use App\Domains\User\Models\User;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ImpersonationController::class)]
final class ImpersonationControllerTest extends TestCase
{
    private User $authorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo(SystemPermission::ManageImpersonation);
    }

    public function test_impersonating_needs_the_impersonation_permission(): void
    {
        $unauthorizedUser = User::factory()->create();
        $this->actingAs($unauthorizedUser);

        $response = $this->post('/impersonate/take/2/web');

        $response->assertForbidden();
    }

    public function test_impersonating_follows_the_url_the_action_returns(): void
    {
        $this->actingAs($this->authorizedUser);

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->with($this->authorizedUser, 2, 'web')
                ->andReturns('https://example.com/dashboard');
        });

        $response = $this->post('/impersonate/take/2/web');

        $response->assertRedirect('https://example.com/dashboard');
    }

    public function test_impersonating_follows_a_path_the_action_returns(): void
    {
        $this->actingAs($this->authorizedUser);

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('/admin/users');
        });

        $response = $this->post('/impersonate/take/2/web');

        $response->assertRedirect('/admin/users');
    }

    public function test_impersonating_someone_who_cannot_be_impersonated_goes_back(): void
    {
        $this->actingAs($this->authorizedUser);

        $this->mock(StartImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('back');
        });

        $response = $this->post('/impersonate/take/2/web');

        $response->assertRedirect('/');
    }

    public function test_leaving_follows_the_url_the_action_returns(): void
    {
        $this->mock(StopImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('https://example.com/dashboard');
        });

        $response = $this->post('/impersonate/leave');

        $response->assertRedirect('https://example.com/dashboard');
    }

    public function test_leaving_can_go_back(): void
    {
        $this->mock(StopImpersonation::class, function ($mock) {
            $mock->expects('__invoke')
                ->andReturns('back');
        });

        $response = $this->post('/impersonate/leave');

        $response->assertRedirect();
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        auth()->logout();

        $response = $this->post(route('impersonate', 2));

        $response->assertRedirect('/app/login');

        $this->assertGuest();
    }

    public function test_impersonating_starts_in_the_app_panel(): void
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

        $response->assertRedirect(Filament::getPanel(AppPanelProvider::ID)->getUrl());
        $this->assertSame($referer, session('impersonation.return_url'));
    }

    // The Impersonate button is on the administrator's view of
    // the person, which they usually can't open themselves.
    public function test_impersonating_from_the_users_page_lands_somewhere_the_person_can_see(): void
    {
        $this->authorizedUser->givePermissionTo(SystemPermission::AccessAdministrationPanel, SystemPermission::ViewUsers);
        $person = User::factory()->create();

        $userPage = config('app.url') . '/administration/users/' . $person->id;

        $response = $this->actingAs($this->authorizedUser)->post('/impersonate/take/' . $person->id, [], ['Referer' => $userPage]);

        $this->assertSame($person->id, auth()->id());
        $this->get($response->headers->get('Location'))->assertOk();
    }

    public function test_the_page_the_administrator_came_from_is_kept_for_leaving(): void
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

    public function test_a_page_on_another_site_is_not_kept_for_leaving(): void
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
