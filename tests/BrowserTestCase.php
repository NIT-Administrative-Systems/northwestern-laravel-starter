<?php

declare(strict_types=1);

namespace Tests;

use App\Domains\Auth\Enums\SystemRole;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use Northwestern\SysDev\Chassis\Testing\Browser\Concerns\InteractsWithBrowser;

/**
 * A test that drives a real browser. The application runs inside the test process, so
 * factories, actingAs() and fakes apply to what the browser does, and the database is
 * refreshed for each test as in a feature test.
 */
abstract class BrowserTestCase extends TestCase
{
    use InteractsWithBrowser;

    protected function setUp(): void
    {
        parent::setUp();

        // The browser loads the built assets.
        $this->withVite();
    }

    protected function tearDown(): void
    {
        pest()->browser()->inLightMode();

        parent::tearDown();
    }

    /**
     * Visit every later page in this test in dark mode, including the ones Chassis's expectations
     * visit themselves.
     */
    protected function useDarkMode(): void
    {
        pest()->browser()->inDarkMode();
    }

    /**
     * Sign in as a person with the Super Administrator role, which holds every permission.
     */
    protected function signInAsSuperAdministrator(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', SystemRole::SuperAdministrator)->sole());

        return $this->signIn($user);
    }

    /**
     * Sign in as a Northwestern person with no roles beyond the default one.
     */
    protected function signInAsNorthwesternUser(): User
    {
        return $this->signIn(User::factory()->create());
    }

    /**
     * Sign in as the person as a real sign-in would load them, with every column: signing out
     * reads the remember token, which a model fresh from its factory doesn't have.
     */
    protected function signIn(User $user): User
    {
        $user = $user->fresh() ?? $user;

        $this->actingAs($user);

        return $user;
    }
}
