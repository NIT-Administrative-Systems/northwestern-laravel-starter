<?php

declare(strict_types=1);

namespace App\Domains\Api;

use App\Domains\Api\Enums\AccessRefusal;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\ValueObjects\AccessDecision;
use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use App\Providers\OAuthServiceProvider;
use Laravel\Mcp\Server\Registrar;

/**
 * Who may do what with an API credential: the one place every page, action, middleware and
 * scheduled sweep asks, so they all agree.
 *
 * Pass the credential's holder: the actor when it's their own credential, another person when
 * an administrator acts on theirs (an API user, for a service client), or null when an
 * administrator acts on a client as a whole, such as revoking an application for everyone.
 *
 * - A feature that is off refuses everything of its kind, except an administrator seeing and
 *   revoking what already exists, so a feature can be shut down cleanly.
 * - Nothing is issued, modified or revoked while impersonating: credentials only change under
 *   the name of the person changing them.
 * - Administrators need Manage API Access. Holders need Create Personal Access Tokens for
 *   personal access tokens and Use MCP for MCP clients.
 * - Manage All satisfies every permission here, whether or not the request carries a token.
 *   Gate::before skips token requests, so permissions are checked here directly.
 */
readonly class CredentialAccess
{
    public function decide(User $actor, CredentialOperation $operation, CredentialKind $kind, ?User $holder): AccessDecision
    {
        $administering = ! $holder instanceof User || $holder->isNot($actor);

        if (! $this->offers($operation, $kind, $administering, $holder instanceof User)) {
            return AccessDecision::refuse(AccessRefusal::Unsupported, $kind);
        }

        if (! $kind->isEnabled() && ! ($administering && in_array($operation, [CredentialOperation::See, CredentialOperation::Revoke], true))) {
            return AccessDecision::refuse(AccessRefusal::FeatureOff, $kind);
        }

        if (in_array($operation, [CredentialOperation::Issue, CredentialOperation::Modify, CredentialOperation::Revoke], true)
            && resolve('impersonate')->isImpersonating()) {
            return AccessDecision::refuse(AccessRefusal::Impersonating, $kind);
        }

        return $administering
            ? $this->administer($actor, $operation, $kind, $holder)
            : $this->hold($actor, $operation, $kind);
    }

    /**
     * The scopes a person may give a credential of this kind: for a personal access token or a
     * connected application, one per API-relevant permission they hold; for an MCP client,
     * `mcp:use` if they may use MCP.
     *
     * @return array<string, string> Scope name => description
     */
    public function grantableScopes(User $holder, CredentialKind $kind): array
    {
        return match ($kind) {
            CredentialKind::PersonalAccessToken, CredentialKind::ConnectedApplication => array_filter(
                OAuthServiceProvider::scopes(),
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
     * Which operations exist. A holder uses their own credentials and sees, issues and revokes
     * their own tokens and connections; an API user only uses its service clients. An
     * administrator sees and revokes anyone's, issues and modifies service clients, and
     * registers and edits applications as a whole.
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

    private function administer(User $administrator, CredentialOperation $operation, CredentialKind $kind, ?User $holder): AccessDecision
    {
        if (! $this->holds($administrator, SystemPermission::ManageApiAccess)) {
            return AccessDecision::refuse(AccessRefusal::MissingPermission, $kind);
        }

        if ($holder instanceof User && $this->isWrongAccountType($holder, $kind)) {
            return AccessDecision::refuse(AccessRefusal::WrongAccountType, $kind);
        }

        if ($holder instanceof User && in_array($operation, [CredentialOperation::Issue, CredentialOperation::Modify], true) && $this->isInactive($holder)) {
            return AccessDecision::refuse(AccessRefusal::AccountInactive, $kind);
        }

        return AccessDecision::allow($kind);
    }

    private function hold(User $holder, CredentialOperation $operation, CredentialKind $kind): AccessDecision
    {
        if ($this->isWrongAccountType($holder, $kind)) {
            return AccessDecision::refuse(AccessRefusal::WrongAccountType, $kind);
        }

        if (in_array($operation, [CredentialOperation::Issue, CredentialOperation::Use], true) && $this->isInactive($holder)) {
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

    /**
     * Only an API user holds service clients, and an API user holds nothing else.
     */
    private function isWrongAccountType(User $holder, CredentialKind $kind): bool
    {
        return ($kind === CredentialKind::ServiceClient) !== ($holder->auth_type === AuthType::API);
    }

    /**
     * Read from the raw attributes: a user built in memory, or selected with fewer columns, may
     * not have them, and the application refuses access to attributes that weren't retrieved.
     */
    private function isInactive(User $user): bool
    {
        $attributes = $user->getAttributes();

        return ($attributes['deleted_at'] ?? null) !== null || (bool) ($attributes['netid_inactive'] ?? false);
    }

    /**
     * Spatie's direct check, not the Gate: Gate::before grants Manage All only without a token,
     * and these rules must not depend on whether one is attached.
     */
    private function holds(User $user, SystemPermission $permission): bool
    {
        return $user->checkPermissionTo(SystemPermission::ManageAll) || $user->checkPermissionTo($permission);
    }
}
