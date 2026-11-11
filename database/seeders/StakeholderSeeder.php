<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\SystemRole;
use App\Domains\Access\Models\Role;
use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Gives the stakeholders named in the configuration their roles.
 *
 * DemoSeeder doesn't call it, because it looks people up in Directory Search.
 *
 * Run it on its own in a deployed environment.
 *
 * @see DemoSeeder
 */
class StakeholderSeeder extends Seeder
{
    public function __construct(
        protected FindOrUpdateUserFromDirectory $findOrUpdateUserFromDirectory
    ) {
        //
    }

    public function run(): void
    {
        $this->superAdmins();
    }

    /**
     * Makes the NetIDs in SUPER_ADMIN_NETIDS Super Administrators.
     *
     * It's comma-separated, so each environment names its own without a code change.
     */
    protected function superAdmins(): void
    {
        $userNetIds = $this->parseStakeholderConfig(config('platform.stakeholders.super_admins', []));

        if ($userNetIds === []) {
            $this->command->warn('No NetIDs have been configured in "SUPER_ADMIN_NETIDS"');

            return;
        }

        $this->createAndAssignRole(
            $userNetIds,
            Role::query()->where('name', SystemRole::SuperAdministrator)->firstOrFail()
        );
    }

    /**
     * @param  list<string>  $netIds
     * @param  Role|Collection<int, Role>  $roles
     */
    protected function createAndAssignRole(array $netIds, Role|Collection $roles): void
    {
        if ($roles instanceof Role) {
            $roles = collect([$roles]);
        }

        foreach ($netIds as $netId) {
            try {
                $user = retry(3, fn () => ($this->findOrUpdateUserFromDirectory)($netId));

                if (! $user instanceof User) {
                    $this->command->getOutput()->writeln(
                        "<comment>User not found in directory, skipping:</comment> {$netId}"
                    );

                    continue;
                }

                $missingRoles = $roles->reject(fn (Role $role) => $user->hasRole($role));

                if ($missingRoles->isNotEmpty()) {
                    $user->assignRoleWithAudit($missingRoles->all(), RoleModificationOrigin::System);
                }
            } catch (Throwable) {
                $this->command->getOutput()->writeln(
                    "<error>Could not load directory info for user, skipping:</error> {$netId}"
                );
            }
        }
    }

    /**
     * Turn a comma-separated string or a list of NetIDs into a clean list.
     *
     * @param  string|list<string>  $config
     * @return list<string>
     */
    protected function parseStakeholderConfig(string|array $config): array
    {
        if (is_string($config)) {
            $config = explode(',', $config);
        }

        return array_values(array_filter(
            array_map(trim(...), $config),
            filled(...),
        ));
    }
}
