<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Access\Actions;

use App\Domains\Access\Actions\AddPersonToRole;
use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Exceptions\PersonNotAdded;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\RoleType;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\Core\Models\Audit;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Feature\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectoryTest;
use Tests\TestCase;

#[CoversClass(AddPersonToRole::class)]
#[CoversClass(PersonNotAdded::class)]
final class AddPersonToRoleTest extends TestCase
{
    private User $administrator;

    private Role $role;

    /** @var array<string, array<string, mixed>> */
    private array $directory = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['platform.wildcard_photo_sync' => false, 'local-auth.enabled' => true]);

        $this->administrator = User::factory()->affiliate()->create();
        $this->administrator->givePermissionTo(SystemPermission::AssignRoles, SystemPermission::CreateUsers);
        $this->role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create(['name' => 'Editors']);

        $directorySearch = $this->createStub(DirectorySearch::class);
        $directorySearch->method('lookup')->willReturnCallback(fn (string $value): array|false => $this->directory[strtolower($value)] ?? false);
        $this->app->instance(DirectorySearch::class, $directorySearch);
    }

    public function test_a_netid_in_the_directory_gets_an_account_and_the_role(): void
    {
        $this->inDirectory('abc123');

        $user = $this->add('abc123');

        $this->assertSame(AuthType::SSO, $user->auth_type);
        $this->assertSame('abc123', $user->username);
        $this->assertTrue($user->hasRole($this->role));
    }

    public function test_the_assignment_is_audited_as_a_bulk_add_by_the_administrator(): void
    {
        $this->inDirectory('abc123');
        $this->actingAs($this->administrator);

        $user = $this->add('abc123');

        $audit = Audit::query()->where('auditable_id', $user->getKey())->where('event', AuditEvent::RoleAssigned->value)->where('tags', RoleModificationOrigin::BulkAssignment->value)->sole();
        $this->assertSame($this->administrator->getKey(), $audit->user_id);
    }

    public function test_an_existing_account_found_by_email_gets_the_role(): void
    {
        $existing = User::factory()->affiliate()->create(['auth_type' => AuthType::Local, 'email' => 'guest@example.com']);

        $user = $this->add('Guest@Example.com');

        $this->assertTrue($existing->is($user));
        $this->assertTrue($existing->hasRole($this->role));
    }

    public function test_an_email_address_that_matches_no_one_becomes_a_local_account(): void
    {
        $user = $this->add('new.guest@example.com', firstName: ' Willie ', lastName: 'Wildcat');

        $this->assertSame(AuthType::Local, $user->auth_type);
        $this->assertSame('new.guest@example.com', $user->email);
        $this->assertSame('Willie', $user->first_name);
        $this->assertSame('Wildcat', $user->last_name);
        $this->assertTrue($user->hasRole($this->role));
    }

    public function test_a_local_account_can_start_without_a_name(): void
    {
        $user = $this->add('nameless@example.com', firstName: '');

        $this->assertNull($user->first_name);
        $this->assertSame([], $user->job_titles);
    }

    public function test_a_netid_missing_from_the_directory_is_not_added(): void
    {
        $this->assertNotAdded('zzz999', 'No one with the NetID or email address zzz999 is in the Northwestern Directory.');
    }

    // A Northwestern address missing from the directory is a typo or a lapsed account.
    public function test_a_northwestern_email_missing_from_the_directory_does_not_become_a_local_account(): void
    {
        $this->assertNotAdded('typo@u.northwestern.edu', 'No one with the NetID or email address typo@u.northwestern.edu is in the Northwestern Directory.');
        $this->assertFalse(User::query()->whereEmailEquals('typo@u.northwestern.edu')->exists());
    }

    // The directory answered, but with a record
    // too incomplete to make an account from.
    public function test_an_incomplete_directory_entry_is_not_added(): void
    {
        $this->directory['half123'] = ['uid' => 'half123'];

        $this->assertNotAdded('half123', 'No one with the NetID or email address half123 is in the Northwestern Directory.');
        $this->assertFalse(User::query()->whereUsernameEquals('half123')->exists());
    }

    public function test_no_local_account_is_created_while_email_code_sign_in_is_off(): void
    {
        config(['local-auth.enabled' => false]);

        $this->assertNotAdded('guest@example.com', 'guest@example.com isn\'t in the Northwestern Directory, and email-code sign-in is off, so there\'s no local account to create.');
    }

    public function test_a_deactivated_local_account_is_not_created_again(): void
    {
        User::factory()->affiliate()->create(['auth_type' => AuthType::Local, 'email' => 'gone@example.com'])->delete();

        $this->assertNotAdded('gone@example.com', 'The account for gone@example.com is deactivated. Reactivate it on their page in Administration first.');
        $this->assertSame(1, User::withTrashed()->whereEmailEquals('gone@example.com')->count());
    }

    // Only Reactivate brings an account back, so
    // a deactivated NetID is listed instead.
    public function test_a_deactivated_netid_user_is_not_restored(): void
    {
        $this->inDirectory('abc123');
        $deactivated = User::factory()->create(['username' => 'abc123']);
        $deactivated->delete();

        $this->assertNotAdded('abc123', 'The account for abc123 is deactivated. Reactivate it on their page in Administration first.');
        $this->assertSoftDeleted($deactivated);
        $this->assertFalse($deactivated->fresh()?->hasRole($this->role));
    }

    public function test_an_api_user_is_not_added(): void
    {
        User::factory()->api()->create(['username' => 'integration']);

        $this->assertNotAdded('integration', 'integration is an API user. Assign API users roles one at a time.');
    }

    public function test_someone_who_already_has_the_role_is_reported(): void
    {
        $this->inDirectory('abc123');
        $this->add('abc123');

        $this->assertNotAdded('abc123', 'abc123 already has the Editors role.');
    }

    public function test_nobody_adds_themselves_even_with_manage_all(): void
    {
        $this->administrator->givePermissionTo(SystemPermission::ManageAll);
        $this->administrator->update(['email' => 'me@example.com']);

        $this->assertNotAdded('me@example.com', 'You can\'t add yourself to a role.');
        $this->assertFalse($this->administrator->fresh()?->hasRole($this->role));
    }

    public function test_a_blank_entry_is_not_added(): void
    {
        $this->assertNotAdded('  ', 'There\'s no NetID or email address.');
    }

    public function test_assignment_locked_roles_are_refused_even_for_manage_all(): void
    {
        $this->administrator->givePermissionTo(SystemPermission::ManageAll);
        $this->role->update(['assignment_locked' => true]);

        $this->assertRefused('This role is assignment-locked and can\'t be assigned here.');
    }

    public function test_api_integration_roles_are_refused(): void
    {
        $this->role->role_type()->associate(RoleType::query()->where('slug', RoleTypeEnum::ApiIntegration)->firstOrFail())->save();

        $this->assertRefused('API Integration roles are assigned to API users one at a time.');
    }

    public function test_it_takes_create_users_as_well_as_assign_roles(): void
    {
        $this->administrator->revokePermissionTo(SystemPermission::CreateUsers);

        $this->assertRefused('You need permission to assign this role and to create users to add people in bulk.');
    }

    public function test_the_roles_offered_are_the_ones_people_may_be_added_to(): void
    {
        $locked = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create(['name' => 'Locked', 'assignment_locked' => true]);
        $other = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create(['name' => 'Authors']);

        $roles = resolve(AddPersonToRole::class)->rolesFor($this->administrator);

        $this->assertSame('Authors', $roles[$other->getKey()]);
        $this->assertSame('Editors', $roles[$this->role->getKey()]);
        $this->assertArrayNotHasKey($locked->getKey(), $roles);
        $this->assertSame([], resolve(AddPersonToRole::class)->rolesFor(User::factory()->create()));
    }

    private function add(string $person, ?string $firstName = null, ?string $lastName = null): User
    {
        return resolve(AddPersonToRole::class)($this->role, $person, $this->administrator, $firstName, $lastName);
    }

    private function inDirectory(string $netId): void
    {
        $this->directory[$netId] = ['mail' => "{$netId}@northwestern.edu"] + FindOrUpdateUserFromDirectoryTest::studentData($netId);
    }

    private function assertNotAdded(string $person, string $reason): void
    {
        try {
            $this->add($person);
            $this->fail("{$person} was added.");
        } catch (PersonNotAdded $exception) {
            $this->assertSame($reason, $exception->getMessage());
        }
    }

    private function assertRefused(string $reason): void
    {
        $this->inDirectory('abc123');

        try {
            $this->add('abc123');
            $this->fail('The role was given.');
        } catch (AuthorizationException $exception) {
            $this->assertSame($reason, $exception->getMessage());
        }

        $this->assertFalse(User::query()->where('username', 'abc123')->exists());
    }
}
