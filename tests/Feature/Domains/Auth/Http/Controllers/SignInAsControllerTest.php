<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Http\Controllers;

use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Models\Role;
use App\Domains\Auth\Http\Controllers\SignInAsController;
use App\Domains\User\Models\User;
use App\Providers\Filament\AppPanelProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The route is registered while the application boots, so each test boots in the environment it
 * needs: `local` by default, or the one its data provider names.
 */
#[CoversClass(SignInAsController::class)]
#[CoversClass(AppPanelProvider::class)]
final class SignInAsControllerTest extends TestCase
{
    public function createApplication(): Application
    {
        $variables = ['APP_ENV' => $this->providedData()[0] ?? 'local', 'APP_URL' => $this->providedData()[1] ?? null];
        $previous = [];

        foreach (array_filter($variables) as $name => $value) {
            $previous[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }

        try {
            return parent::createApplication();
        } finally {
            foreach ($previous as $name => [$env, $server, $process]) {
                [$_ENV[$name], $_SERVER[$name]] = [$env, $server];
                putenv($process === false ? $name : "{$name}={$process}");
            }
        }
    }

    public function test_it_signs_in_as_the_user_and_records_the_sign_in(): void
    {
        $admin = $this->seededAdmin();

        $this->get('/app/login/as/nuit.admin')->assertRedirect('/');

        $this->assertAuthenticatedAs($admin);
        $this->assertSame(1, $admin->login_records()->count());
    }

    public function test_it_returns_users_to_the_page_they_asked_for(): void
    {
        $this->seededAdmin();

        $this->withSession(['url.intended' => url('/app/gallery')])
            ->get('/app/login/as/nuit.admin')
            ->assertRedirect(url('/app/gallery'));
    }

    public function test_api_users_and_unknown_usernames_are_not_found(): void
    {
        User::factory()->api()->create(['username' => 'api-nuit']);

        $this->get('/app/login/as/api-nuit')->assertNotFound();
        $this->get('/app/login/as/nobody')->assertNotFound();
        $this->assertGuest();
    }

    public function test_the_sign_in_page_offers_the_seeded_demo_users(): void
    {
        $this->seededAdmin();
        User::factory()->create(['username' => 'generic.user', 'first_name' => 'Generic', 'last_name' => 'User']);

        $this->get('/app/login')
            ->assertOk()
            ->assertSeeInOrder(['Development', 'Sign In As…', 'NUIT Administrator', 'Super Administrator', 'Generic User', 'Northwestern User'])
            ->assertSee(url('/app/login/as/nuit.admin'), escape: false)
            ->assertSee('Only in local environments.');
    }

    // With no SSO and no email codes, the page would otherwise say no sign-in methods are available.
    public function test_it_replaces_the_no_sign_in_methods_callout(): void
    {
        config(['local-auth.enabled' => false]);
        $this->seededAdmin();

        $this->get('/app/login')
            ->assertOk()
            ->assertSee('NUIT Administrator')
            ->assertDontSee('No sign-in methods are set up.');
    }

    /** @return iterable<string, array{string, string}> */
    public static function localOverPlainHttp(): iterable
    {
        yield 'local at http://localhost:8060' => ['local', 'http://localhost:8060'];
    }

    // Worktrees and agents serve over plain HTTP on any port; forced HTTPS and secure-only session
    // cookies would send them to an address that doesn't exist and drop the session.
    #[DataProvider('localOverPlainHttp')]
    public function test_it_works_over_plain_http_locally(string $environment, string $appUrl): void
    {
        $this->seededAdmin();

        $location = (string) $this->get('/app/login/as/nuit.admin')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('http://', $location);
        $this->assertFalse(config('session.secure'));
    }

    /**
     * Not `ci`: booting it loads `.env.ci`, which points at the CI database.
     *
     * @return iterable<string, array{string}>
     */
    public static function environmentsWithoutSignInAs(): iterable
    {
        foreach (['production', 'develop', 'qa', 'testing'] as $environment) {
            yield $environment => [$environment];
        }
    }

    #[DataProvider('environmentsWithoutSignInAs')]
    public function test_the_route_exists_only_in_local(string $environment): void
    {
        $this->assertSame($environment, app()->environment());
        $this->assertFalse(Route::has('filament.app.auth.login-as'));
    }

    private function seededAdmin(): User
    {
        $admin = User::factory()->create(['username' => 'nuit.admin', 'first_name' => 'NUIT', 'last_name' => 'Administrator']);
        $admin->roles()->attach(Role::query()->whereHas('role_type', fn (\Illuminate\Contracts\Database\Query\Builder $query) => $query->where('slug', RoleTypeEnum::SystemManaged))->where('name', 'Super Administrator')->firstOrFail());

        return $admin;
    }
}
