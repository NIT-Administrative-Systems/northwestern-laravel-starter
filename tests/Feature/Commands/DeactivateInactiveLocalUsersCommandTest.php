<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\DeactivateInactiveLocalUsersCommand;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DeactivateInactiveLocalUsersCommand::class)]
final class DeactivateInactiveLocalUsersCommandTest extends TestCase
{
    public function test_it_deactivates_local_accounts_with_no_sign_in_for_the_configured_days(): void
    {
        config(['platform.accounts.local_inactivity_days' => 90]);
        $lapsed = $this->localUser(createdDaysAgo: 200, signedInDaysAgo: 91);
        $neverSignedIn = $this->localUser(createdDaysAgo: 91);
        $active = $this->localUser(createdDaysAgo: 200, signedInDaysAgo: 89);
        $new = $this->localUser(createdDaysAgo: 89);
        $northwestern = User::factory()->create(['created_at' => now()->subDays(200)]);

        $this->artisan(DeactivateInactiveLocalUsersCommand::class)
            ->expectsOutputToContain('Deactivated 2 local account(s) with no sign-in for 90 days.')
            ->assertSuccessful();

        $this->assertSoftDeleted($lapsed);
        $this->assertSoftDeleted($neverSignedIn);
        $this->assertNotSoftDeleted($active);
        $this->assertNotSoftDeleted($new);
        $this->assertNotSoftDeleted($northwestern);
    }

    public function test_local_accounts_never_expire_by_default(): void
    {
        $lapsed = $this->localUser(createdDaysAgo: 2000);

        $this->artisan(DeactivateInactiveLocalUsersCommand::class)
            ->expectsOutputToContain('Local accounts never expire.')
            ->assertSuccessful();

        $this->assertNotSoftDeleted($lapsed);
    }

    private function localUser(int $createdDaysAgo, ?int $signedInDaysAgo = null): User
    {
        $user = User::factory()->affiliate()->create(['auth_type' => AuthType::Local, 'created_at' => now()->subDays($createdDaysAgo)]);

        if ($signedInDaysAgo !== null) {
            UserLoginRecord::factory()->for($user)->create(['logged_in_at' => now()->subDays($signedInDaysAgo)]);
        }

        return $user;
    }
}
