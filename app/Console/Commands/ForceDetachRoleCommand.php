<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Auth\Enums\RoleModificationOriginEnum;
use App\Domains\Auth\Models\Role;
use App\Domains\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

class ForceDetachRoleCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'role:force-detach
        {user : The username or ID of the user}
        {role : The name of the role to detach}
        {--reason= : The reason for this emergency detachment}
        {--force : Skip confirmation prompt (for non-interactive environments)}';

    protected $description = 'Emergency detach an assignment-locked role from a user with audit trail';

    public function handle(): int
    {
        $userIdentifier = $this->argument('user');
        $user = User::where('username', $userIdentifier)
            ->orWhere('id', is_numeric($userIdentifier) ? $userIdentifier : 0)
            ->first();

        if (! $user) {
            $this->components->error("User not found: {$userIdentifier}");

            return self::FAILURE;
        }

        $roleName = $this->argument('role');
        $role = Role::where('name', $roleName)->first();

        if (! $role) {
            $this->components->error("Role not found: {$roleName}");

            return self::FAILURE;
        }

        if (! $user->hasRole($role)) {
            $this->components->error("User {$user->username} does not have the {$role->name} role.");

            return self::FAILURE;
        }

        $reason = $this->option('reason');

        if (blank($reason) && $this->option('force')) {
            $this->components->error('The --reason option is required when using --force.');

            return self::FAILURE;
        }

        $reason = $reason ?: text(
            label: 'Reason for this emergency detachment',
            required: 'A reason is required for audit trail purposes.',
            hint: 'This will be recorded in the audit log.',
        );

        $this->newLine();
        $this->components->warn('About to force-detach a role assignment.');
        $this->components->twoColumnDetail('User', "{$user->clerical_name} ({$user->username})");
        $this->components->twoColumnDetail('Role', $role->name);
        $this->components->twoColumnDetail('Assignment Locked', $role->isAssignmentLocked() ? 'Yes' : 'No');
        $this->components->twoColumnDetail('Reason', $reason);

        if (! $this->option('force') && ! confirm('Do you want to proceed?', default: false)) {
            $this->components->warn('Cancelled.');

            return self::SUCCESS;
        }

        $user->removeRoleWithAudit(
            $role,
            RoleModificationOriginEnum::SYSTEM,
            ['reason' => $reason, 'command' => 'role:force-detach'],
        );

        $this->components->success("Role \"{$role->name}\" detached from user \"{$user->username}\" with audit trail.");

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'user' => fn () => search(
                label: 'Search for a user',
                options: function (string $search): array {
                    if (blank($search)) {
                        return [];
                    }

                    return User::query()
                        ->searchByName($search)
                        ->orWhere('username', 'ilike', "%{$search}%")
                        ->limit(10)
                        ->get()
                        ->mapWithKeys(fn (User $user) => [
                            $user->username => "{$user->clerical_name} ({$user->username})",
                        ])
                        ->all();
                },
                placeholder: 'Search by name or NetID…',
                hint: 'Type to search for a user by name or NetID.',
            ),
            'role' => fn () => search(
                label: 'Search for a role',
                options: function (string $search): array {
                    if (blank($search)) {
                        return [];
                    }

                    return Role::query()
                        ->where('name', 'ilike', "%{$search}%")
                        ->limit(10)
                        ->pluck('name', 'name')
                        ->all();
                },
                placeholder: 'Search by role name…',
            ),
        ];
    }
}
