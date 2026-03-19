<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Auth\Enums\RoleModificationOrigin;
use App\Domains\Auth\Enums\RoleTypeEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Seed the environment with users who have specific roles in the application. These are users that
 * at a minimum are affiliated with Northwestern University and have a NetID.
 *
 * This is called from the {@see DemoSeeder}, but it can additionally be run
 * once in production to initialize users with their roles.
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

    protected function superAdmins(): void
    {
        /**
         * Assign the Super Administrator role to a predefined list of NetIDs.
         *
         * The list is sourced from the `SUPER_ADMIN_NETIDS` environment variable,
         * which should contain a comma-separated list of NetIDs. This allows teams
         * to easily configure users per environment without modifying code or
         * manually processing user assignments.
         */
        $userNetIds = $this->parseStakeholderConfig(config('platform.stakeholders.super_admins', []));

        if ($userNetIds === []) {
            $this->command->warn('No NetIDs have been configured in "SUPER_ADMIN_NETIDS"');

            return;
        }

        $this->createAndAssignRole(
            $userNetIds,
            Role::whereHas('role_type', fn ($query) => $query->where('slug', RoleTypeEnum::SystemManaged))->firstOrFail()
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

                $user->assignRoleWithAudit($roles->all(), RoleModificationOrigin::System);
            } catch (Throwable) {
                $this->command->getOutput()->writeln(
                    "<error>Could not load directory info for user, skipping:</error> {$netId}"
                );
            }
        }
    }

    /**
     * Parses a stakeholder configuration value, which can be either a comma-separated string
     * of NetIDs or an array of NetIDs, into a clean array of NetIDs.
     *
     * @param  string|list<string>  $config  The configuration value from `config/platform.php`.
     * @return list<string> A cleaned array of NetIDs.
     */
    protected function parseStakeholderConfig(string|array $config): array
    {
        if (is_string($config)) {
            $config = explode(',', $config);
        }

        return array_values(array_filter(
            array_map('trim', $config),
            fn (string $netId) => filled($netId),
        ));
    }
}
