<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Pages\Auth;

use App\Domains\User\Models\User;
use App\Filament\App\Pages\Auth\Login;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Login::class)]
final class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);

        // The SSO routes are only registered when SSO is configured at boot.
        if (! Route::has('login-oauth-redirect')) {
            Route::get('auth/azure-ad/redirect', fn () => null)->name('login-oauth-redirect');
        }

        if (! Route::has('login-websso')) {
            Route::get('auth/websso/login', fn () => null)->name('login-websso');
        }

        Route::getRoutes()->refreshNameLookups();

        config([
            'nusoa.sso.apigeeApiKey' => null,
            'nusoa.sso.strategy' => null,
            'services.northwestern-azure.client_id' => null,
            'services.northwestern-azure.client_secret' => null,
        ]);
    }

    public function test_guests_of_either_panel_are_sent_to_the_login_page(): void
    {
        $this->get('/app')->assertRedirect('/app/login');
        $this->get('/administration')->assertRedirect('/app/login');
    }

    public function test_page_renders_over_http(): void
    {
        config(['local-auth.enabled' => true]);

        $this->get('/app/login')->assertOk()->assertSee('Sign in with email');
    }

    public function test_shows_entra_and_email_options_when_both_are_configured(): void
    {
        $this->configureEntra();
        config(['local-auth.enabled' => true]);

        Livewire::test(Login::class)
            ->assertSee('Sign in with NetID')
            ->assertSeeHtml(route('login-oauth-redirect'))
            ->assertSee('Sign in with email')
            ->assertDontSee('No sign-in methods available');
    }

    public function test_prefers_websso_over_entra_when_both_are_configured(): void
    {
        $this->configureEntra();
        config(['local-auth.enabled' => true, 'nusoa.sso.apigeeApiKey' => 'test-api-key']);

        Livewire::test(Login::class)
            ->assertSeeHtml(route('login-websso'))
            ->assertDontSeeHtml(route('login-oauth-redirect'));
    }

    public function test_uses_websso_for_the_forgerock_direct_strategy(): void
    {
        config(['local-auth.enabled' => true, 'nusoa.sso.strategy' => 'forgerock-direct']);

        Livewire::test(Login::class)->assertSeeHtml(route('login-websso'));
    }

    public function test_redirects_to_entra_when_it_is_the_only_method(): void
    {
        $this->configureEntra();
        config(['local-auth.enabled' => false]);

        Livewire::test(Login::class)->assertRedirect(route('login-oauth-redirect'));
    }

    public function test_redirects_to_websso_when_it_is_the_only_method(): void
    {
        config(['local-auth.enabled' => false, 'nusoa.sso.apigeeApiKey' => 'test-api-key']);

        Livewire::test(Login::class)->assertRedirect(route('login-websso'));
    }

    public function test_renders_instead_of_redirecting_in_ci(): void
    {
        $this->configureEntra();
        config(['local-auth.enabled' => false]);
        $this->app->detectEnvironment(fn () => 'ci');

        Livewire::test(Login::class)
            ->assertNoRedirect()
            ->assertSee('Sign in with NetID')
            ->assertDontSee('Sign in with email');
    }

    public function test_explains_when_no_sign_in_methods_are_configured(): void
    {
        config(['local-auth.enabled' => false]);

        Livewire::test(Login::class)
            ->assertNoRedirect()
            ->assertSee('No sign-in methods available')
            ->assertDontSee('Sign in with NetID');
    }

    public function test_signed_in_users_are_redirected_home(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Login::class)->assertRedirect('/');
    }

    private function configureEntra(): void
    {
        config([
            'services.northwestern-azure.client_id' => 'test-client-id',
            'services.northwestern-azure.client_secret' => 'test-client-secret',
        ]);
    }

    // The site header names the application, so the card's heading is a plain "Sign in"; the browser tab keeps the name.
    public function test_page_has_the_site_header_and_a_plain_heading(): void
    {
        $this->configureEntra();
        config(['local-auth.enabled' => true]);

        $this->get('/app/login')
            ->assertOk()
            ->assertSeeInOrder(['<h1 class="fi-simple-header-heading">', 'Sign in', '</h1>'], escape: false)
            ->assertDontSee('Sign in to')
            ->assertSeeInOrder(['<title>', 'Sign in', ' - ', e(config('app.name')), '</title>'], escape: false)
            ->assertSee('For students, faculty, staff, and affiliates.')
            ->assertSee('For approved external partners without a NetID.')
            ->assertSee('nu-sign-in-divider', escape: false)
            ->assertSee('https://www.northwestern.edu/', escape: false);
    }

    // With single sign-on alone the page redirects to it, so email alone is the one-method case that renders.
    public function test_the_or_divider_only_shows_with_both_methods(): void
    {
        config(['local-auth.enabled' => true]);

        $this->get('/app/login')
            ->assertOk()
            ->assertSee('Sign in with email')
            ->assertDontSee('nu-sign-in-divider', escape: false);
    }
}
