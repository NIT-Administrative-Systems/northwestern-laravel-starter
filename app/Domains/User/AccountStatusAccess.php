<?php

declare(strict_types=1);

namespace App\Domains\User;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\User\Actions\Lifecycle\DeactivateUser;
use App\Domains\User\Actions\Lifecycle\ReactivateUser;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may deactivate or reactivate an account: someone with Edit Users, acting on someone else,
 * under their own name. It's decided here rather than in a policy because Manage All passes every
 * policy through Gate::before, and not even a super-administrator deactivates their own account or
 * changes one while impersonating. A null actor is the system, such as a scheduled sweep.
 *
 * @see DeactivateUser
 * @see ReactivateUser
 */
readonly class AccountStatusAccess
{
    public function decide(?User $actor, User $user): Response
    {
        if (! $actor instanceof User) {
            return Response::allow();
        }

        if ($actor->is($user)) {
            return Response::deny('You can\'t change the status of your own account.');
        }

        if (resolve('impersonate')->isImpersonating()) {
            return Response::deny('Stop impersonating to change the status of an account.');
        }

        return $actor->can(SystemPermission::EditUsers)
            ? Response::allow()
            : Response::deny('You don\'t have permission to change the status of accounts.');
    }
}
