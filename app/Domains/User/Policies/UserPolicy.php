<?php

declare(strict_types=1);

namespace App\Domains\User\Policies;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;

/**
 * Who may see and edit people in Administration. Someone who manages API access without View
 * Users sees only API users, whose service clients {@see CredentialAccess} lets them manage.
 */
class UserPolicy
{
    public function __construct(
        private readonly CredentialAccess $credentials,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewUsers)
            || $this->credentials->decide($user, CredentialOperation::See, CredentialKind::ServiceClient, null)->allowed;
    }

    public function view(User $user, User $viewedUser): bool
    {
        return $user->is($viewedUser)
            || $user->hasPermissionTo(SystemPermission::ViewUsers)
            || ($viewedUser->auth_type === AuthType::API && $this->credentials->decide($user, CredentialOperation::See, CredentialKind::ServiceClient, $viewedUser)->allowed);
    }

    public function edit(User $user, User $editedUser): bool
    {
        return $user->is($editedUser) || $user->hasPermissionTo(SystemPermission::EditUsers);
    }
}
