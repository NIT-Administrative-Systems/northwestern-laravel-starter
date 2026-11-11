<?php

declare(strict_types=1);

namespace App\Domains\Access\Actions;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Exceptions\PersonNotAdded;
use App\Domains\Access\Models\Role;
use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\SignIn;
use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Actions\Local\CreateLocalUser;
use App\Domains\User\Enums\DirectorySearchType;
use App\Domains\User\Exceptions\BadDirectoryEntry;
use App\Domains\User\Models\User;
use App\Domains\User\QueryBuilders\UserBuilder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Gives one person in a list a role, finding or creating their account first. Each person in
 * a pasted list or uploaded CSV goes through this, from the queued import {@see AddPeopleToRole}
 * starts.
 *
 * A NetID, Northwestern email address or employee ID is looked up in the Northwestern Directory
 * and the account created or brought up to date from it. Any other email address that matches no
 * one becomes a local account, which signs in with a code sent to that address. The role is
 * given through {@see User::assignRoleWithAudit()}, so it's audited and checked like any other
 * assignment.
 */
readonly class AddPersonToRole
{
    public function __construct(
        private FindOrUpdateUserFromDirectory $findOrUpdateUserFromDirectory,
        private CreateLocalUser $createLocalUser,
        private SignIn $signIn,
    ) {
    }

    /**
     * Whether `$actor` may add people to `$role` in bulk. It creates accounts as well as giving
     * the role, so it takes Create Users and Assign Roles. Assignment-locked roles are refused
     * even for Manage All, as they are one person at a time, and API Integration roles belong to
     * API users, whom a NetID or email address never finds.
     */
    public function decide(Role $role, User $actor): Response
    {
        if ($role->isAssignmentLocked()) {
            return Response::deny('This role is assignment-locked and can\'t be assigned here.');
        }

        if ($role->role_type?->slug === RoleTypeEnum::ApiIntegration) {
            return Response::deny('API Integration roles are assigned to API users one at a time.');
        }

        if (! Gate::forUser($actor)->allows('attachUser', $role) || ! $actor->can(SystemPermission::CreateUsers)) {
            return Response::deny('You need permission to assign this role and to create users to add people in bulk.');
        }

        return Response::allow();
    }

    /**
     * The roles `$actor` may add people to in bulk, by ID.
     *
     * @return array<int, string>
     */
    public function rolesFor(User $actor): array
    {
        return Role::query()
            ->assignable()
            ->with('role_type')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role): bool => $this->decide($role, $actor)->allowed())
            ->mapWithKeys(fn (Role $role): array => [(int) $role->getKey() => $role->name])
            ->all();
    }

    /**
     * @param  string|null  $firstName  For a new local account
     * @param  string|null  $lastName  For a new local account
     *
     * @throws PersonNotAdded
     * @throws AuthorizationException
     */
    public function __invoke(Role $role, string $person, User $actor, ?string $firstName = null, ?string $lastName = null): User
    {
        $this->decide($role, $actor)->authorize();

        $person = trim($person);

        if ($person === '') {
            throw PersonNotAdded::blank();
        }

        $user = $this->findOrCreate($person, $firstName, $lastName);

        // Only Reactivate brings a deactivated account back, so its history says who did and why.
        if ($user->trashed()) {
            throw PersonNotAdded::deactivated($person);
        }

        // Nobody gives themselves a role, whatever they hold; the list can name them like anyone else.
        if ($user->is($actor)) {
            throw PersonNotAdded::yourself();
        }

        if ($user->hasRole($role)) {
            throw PersonNotAdded::alreadyAssigned($person, $role);
        }

        $user->assignRoleWithAudit($role, RoleModificationOrigin::BulkAssignment);

        return $user;
    }

    /**
     * @throws PersonNotAdded
     */
    private function findOrCreate(string $person, ?string $firstName, ?string $lastName): User
    {
        try {
            $user = ($this->findOrUpdateUserFromDirectory)($person);
        } catch (BadDirectoryEntry) {
            return $this->createLocalUser($person, $firstName, $lastName);
        }

        if ($user instanceof User) {
            return $user;
        }

        // The lookup returns no one for an API user's username or email, and for a person it couldn't find.
        $isApiUser = User::query()->api()
            ->where(fn (UserBuilder $query) => $query->whereUsernameEquals($person)->orWhere(fn (UserBuilder $email) => $email->whereEmailEquals($person)))
            ->exists();

        return $isApiUser ? throw PersonNotAdded::apiUser($person) : $this->createLocalUser($person, $firstName, $lastName);
    }

    /**
     * @throws PersonNotAdded
     */
    private function createLocalUser(string $person, ?string $firstName, ?string $lastName): User
    {
        // A Northwestern address missing from the directory is a typo or a lapsed account, not a guest.
        if (DirectorySearchType::fromSearchValue($person) !== DirectorySearchType::Email
            || Str::endsWith(Str::lower($person), ['@northwestern.edu', '.northwestern.edu'])) {
            throw PersonNotAdded::notInDirectory($person);
        }

        if (User::query()->onlyTrashed()->whereEmailEquals($person)->exists()) {
            throw PersonNotAdded::deactivated($person);
        }

        if (! $this->signIn->offers(SignInMethod::EmailCode)) {
            throw PersonNotAdded::noLocalAccounts($person);
        }

        return ($this->createLocalUser)(
            email: $person,
            firstName: filled($firstName) ? trim($firstName) : null,
            lastName: filled($lastName) ? trim($lastName) : null,
            sendLoginLink: false,
        );
    }
}
