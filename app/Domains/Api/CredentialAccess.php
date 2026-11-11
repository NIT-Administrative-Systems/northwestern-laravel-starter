<?php

declare(strict_types=1);

namespace App\Domains\Api;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Enums\AccessRefusal;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\ValueObjects\AccessDecision;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Models\User;
use Laravel\Mcp\Server\Registrar;

/**
 * Who may do what with an API credential: the one place every page,
 * action, middleware and scheduled sweep asks, so they all agree.
 *
 * Pass the credential's holder: the actor when it's their own
 * credential, or another person when an administrator acts
 * on theirs, such as an API user for a service client.
 *
 * Pass null when an administrator acts on a client as a
 * whole, as in revoking an application for everyone.
 *
 * A null actor is the system, such as a seeder or a scheduled sweep,
 * and may do anything. Impersonation is the one exception, since
 * that means a caller has forgotten to pass the person acting.
 *
 * A feature that is off refuses everything of its kind, except administrators
 * seeing and revoking what already exists, so it can be shut down cleanly.
 *
 * Nothing is issued, modified or revoked while impersonating, since a
 * credential only changes under the name of the person changing it.
 *
 * Administrators need Manage API Access. Holders need the Create Personal Access
 * Tokens permission for personal access tokens, and Use MCP for MCP clients.
 *
 * Manage All satisfies every permission here, whether or not
 * the request carries a token. Gate::before skips requests
 * with a token, so this class checks permissions itself.
 */
readonly class CredentialAccess
{
    public function decide(?User $actor, CredentialOperation $operation, CredentialKind $kind, ?User $holder): AccessDecision
    {
        if (! $actor instanceof User) {
            return $this->isChange($operation) && $this->isImpersonating()
                ? AccessDecision::refuse(AccessRefusal::Impersonating, $kind)
                : AccessDecision::allow($kind);
        }

        $administering = ! $holder instanceof User || $holder->isNot($actor);

        if (! $this->offers($operation, $kind, $administering, $holder instanceof User)) {
            return AccessDecision::refuse(AccessRefusal::Unsupported, $kind);
        }

        if (! $kind->isEnabled() && (! $administering || ! in_array($operation, [CredentialOperation::See, CredentialOperation::Revoke], true))) {
            return AccessDecision::refuse(AccessRefusal::FeatureOff, $kind);
        }

        if ($this->isChange($operation) && $this->isImpersonating()) {
            return AccessDecision::refuse(AccessRefusal::Impersonating, $kind);
        }

        return $administering
            ? $this->decideForAdministrator($actor, $operation, $kind, $holder)
            : $this->decideForHolder($actor, $operation, $kind);
    }

    /**
     * Get the scopes a person may give a credential of this kind. For a personal
     * access token or connected application, one per API-relevant permission
     * they hold, and for an MCP client, `mcp:use` when they may use MCP.
     *
     * @return array<string, string> Scope name => description
     */
    public function grantableScopes(User $holder, CredentialKind $kind): array
    {
        return match ($kind) {
            CredentialKind::PersonalAccessToken, CredentialKind::ConnectedApplication => array_filter(
                ApiScopes::rest(),
                fn (string $scope): bool => $this->holds($holder, SystemPermission::from($scope)),
                ARRAY_FILTER_USE_KEY,
            ),
            CredentialKind::McpClient => $this->holds($holder, SystemPermission::UseMcp)
                ? [Registrar::OAUTH_SCOPE => SystemPermission::UseMcp->description()]
                : [],
            CredentialKind::ServiceClient => [],
        };
    }

    /**
     * Determine whether the operation exists. A holder uses their own
     * credentials and sees, issues and revokes their own tokens and
     * connections, but an API user only uses its service clients.
     *
     * An administrator sees and revokes anyone's credentials, issues and modifies
     * service clients, and registers and changes an application for everyone.
     */
    private function offers(CredentialOperation $operation, CredentialKind $kind, bool $administering, bool $forHolder): bool
    {
        if (! $administering) {
            return $kind === CredentialKind::ServiceClient
                ? $operation === CredentialOperation::Use
                : $operation !== CredentialOperation::Modify;
        }

        if (in_array($operation, [CredentialOperation::See, CredentialOperation::Revoke], true)) {
            return true;
        }

        if (in_array($operation, [CredentialOperation::Issue, CredentialOperation::Modify], true)) {
            return $forHolder ? $kind === CredentialKind::ServiceClient : $kind === CredentialKind::ConnectedApplication;
        }

        return false;
    }

    private function decideForAdministrator(User $administrator, CredentialOperation $operation, CredentialKind $kind, ?User $holder): AccessDecision
    {
        if (! $this->holds($administrator, SystemPermission::ManageApiAccess)) {
            return AccessDecision::refuse(AccessRefusal::MissingPermission, $kind);
        }

        if ($holder instanceof User && $this->isWrongAccountType($holder, $kind)) {
            return AccessDecision::refuse(AccessRefusal::WrongAccountType, $kind);
        }

        if ($holder instanceof User && in_array($operation, [CredentialOperation::Issue, CredentialOperation::Modify], true) && $holder->isInactive()) {
            return AccessDecision::refuse(AccessRefusal::AccountInactive, $kind);
        }

        return AccessDecision::allow($kind);
    }

    private function decideForHolder(User $holder, CredentialOperation $operation, CredentialKind $kind): AccessDecision
    {
        if ($this->isWrongAccountType($holder, $kind)) {
            return AccessDecision::refuse(AccessRefusal::WrongAccountType, $kind);
        }

        if (in_array($operation, [CredentialOperation::Issue, CredentialOperation::Use], true) && $holder->isInactive()) {
            return AccessDecision::refuse(AccessRefusal::AccountInactive, $kind);
        }

        $permission = match (true) {
            $kind === CredentialKind::PersonalAccessToken && $operation !== CredentialOperation::Revoke => SystemPermission::CreatePersonalAccessTokens,
            $kind === CredentialKind::McpClient && in_array($operation, [CredentialOperation::Issue, CredentialOperation::Use], true) => SystemPermission::UseMcp,
            default => null,
        };

        if ($permission instanceof SystemPermission && ! $this->holds($holder, $permission)) {
            return AccessDecision::refuse(AccessRefusal::MissingPermission, $kind);
        }

        return AccessDecision::allow($kind);
    }

    private function isChange(CredentialOperation $operation): bool
    {
        return in_array($operation, [CredentialOperation::Issue, CredentialOperation::Modify, CredentialOperation::Revoke], true);
    }

    private function isImpersonating(): bool
    {
        return resolve('impersonate')->isImpersonating();
    }

    /**
     * Only an API user holds service clients, and an API user holds nothing else.
     */
    private function isWrongAccountType(User $holder, CredentialKind $kind): bool
    {
        return ($kind === CredentialKind::ServiceClient) !== ($holder->auth_type === AuthType::API);
    }

    /**
     * Determine whether the user holds a permission through Spatie directly,
     * not the Gate. Gate::before grants Manage All only without a token,
     * and these rules must not depend on whether a token is attached.
     */
    private function holds(User $user, SystemPermission $permission): bool
    {
        return $user->checkPermissionTo(SystemPermission::ManageAll) || $user->checkPermissionTo($permission);
    }
}
