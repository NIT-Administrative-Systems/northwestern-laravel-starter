<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\User\Actions\Directory\CreateUserByLookup;
use App\Domains\User\Enums\RoleTypeEnum;
use App\Domains\User\Models\Role;
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
    public function run(CreateUserByLookup $createByLookup): void
    {
        $this->superAdmins($createByLookup);
    }

    protected function superAdmins(CreateUserByLookup $createByLookup): void
    {
        /**
         * Assign the Super Administrator role to a predefined list of NetIDs.
         *
         * The list is sourced from the `SUPER_ADMIN_NETIDS` environment variable,
         * which should contain a comma-separated list of NetIDs. This allows teams
         * to easily configure users per environment without modifying code or
         * manually processing user assignments.
         */
        $userNetIds = array_filter(
            array_map('trim', explode(',', config('platform.stakeholders.super_admins', ''))),
            fn ($netId) => filled($netId),
        );

        if (empty($userNetIds)) {
            $this->command->warn('No NetIDs have been configured in "SUPER_ADMIN_NETIDS"');

            return;
        }

        $this->createAndAssignRole(
            $createByLookup,
            $userNetIds,
            Role::whereHas('role_type', fn ($query) => $query->where('slug', RoleTypeEnum::SYSTEM_MANAGED))->firstOrFail()
        );
    }

    /**
     * @param  list<string>  $netIds
     * @param  Role|Collection<int, Role>  $roles
     */
    protected function createAndAssignRole(CreateUserByLookup $createByLookup, array $netIds, Role|Collection $roles): void
    {
        if ($roles instanceof Role) {
            $roles = collect([$roles]);
        }

        foreach ($netIds as $netId) {
            try {
                $user = retry(3, fn () => $createByLookup($netId));
                $user->roles()->sync($roles->map->id);
            } catch (Throwable) {
                $this->command->getOutput()->writeln(
                    "<error>Could not load directory info for user, skipping:</error> {$netId}"
                );
            }
        }
    }
}
