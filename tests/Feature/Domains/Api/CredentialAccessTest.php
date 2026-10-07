<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\AccessRefusal;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\ValueObjects\AccessDecision;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\AccessToken;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every rule for who may do what with an API credential, at the one interface that answers.
 */
#[CoversClass(CredentialAccess::class)]
#[CoversClass(AccessDecision::class)]
#[CoversClass(AccessRefusal::class)]
final class CredentialAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['api.enabled' => true, 'mcp.enabled' => true]);
    }

    public function test_a_holder_needs_the_permission_their_credential_kind_asks_for(): void
    {
        $person = $this->person();
        $tokenHolder = $this->person(SystemPermission::CreatePersonalAccessTokens);
        $mcpUser = $this->person(SystemPermission::UseMcp);

        foreach ([CredentialOperation::See, CredentialOperation::Issue, CredentialOperation::Use] as $operation) {
            $this->assertRefused(AccessRefusal::MissingPermission, $person, $operation, CredentialKind::PersonalAccessToken, $person);
            $this->assertAllowed($tokenHolder, $operation, CredentialKind::PersonalAccessToken, $tokenHolder);
        }

        // Giving up a credential never needs a permission.
        $this->assertAllowed($person, CredentialOperation::Revoke, CredentialKind::PersonalAccessToken, $person);

        foreach ([CredentialOperation::Issue, CredentialOperation::Use] as $operation) {
            $this->assertRefused(AccessRefusal::MissingPermission, $person, $operation, CredentialKind::McpClient, $person);
            $this->assertAllowed($mcpUser, $operation, CredentialKind::McpClient, $mcpUser);
        }

        $this->assertAllowed($person, CredentialOperation::See, CredentialKind::McpClient, $person);
        $this->assertAllowed($person, CredentialOperation::Revoke, CredentialKind::McpClient, $person);

        // Anyone may connect an application; what it may do is limited by their scopes.
        foreach ([CredentialOperation::See, CredentialOperation::Issue, CredentialOperation::Revoke, CredentialOperation::Use] as $operation) {
            $this->assertAllowed($person, $operation, CredentialKind::ConnectedApplication, $person);
        }
    }

    public function test_an_api_user_only_uses_its_service_clients(): void
    {
        $apiUser = User::factory()->api()->create();

        $this->assertAllowed($apiUser, CredentialOperation::Use, CredentialKind::ServiceClient, $apiUser);

        foreach ([CredentialOperation::See, CredentialOperation::Issue, CredentialOperation::Modify, CredentialOperation::Revoke] as $operation) {
            $this->assertRefused(AccessRefusal::Unsupported, $apiUser, $operation, CredentialKind::ServiceClient, $apiUser);
        }

        $apiUser->givePermissionTo(SystemPermission::CreatePersonalAccessTokens);
        $this->assertRefused(AccessRefusal::WrongAccountType, $apiUser, CredentialOperation::Use, CredentialKind::PersonalAccessToken, $apiUser);

        $person = $this->person();
        $this->assertRefused(AccessRefusal::WrongAccountType, $person, CredentialOperation::Use, CredentialKind::ServiceClient, $person);
    }

    public function test_a_holder_never_modifies_their_own_credentials(): void
    {
        $holder = $this->person(SystemPermission::CreatePersonalAccessTokens, SystemPermission::UseMcp);

        foreach ([CredentialKind::PersonalAccessToken, CredentialKind::ConnectedApplication, CredentialKind::McpClient] as $kind) {
            $this->assertRefused(AccessRefusal::Unsupported, $holder, CredentialOperation::Modify, $kind, $holder);
        }
    }

    public function test_a_deactivated_or_deleted_holder_can_no_longer_issue_or_use(): void
    {
        $inactive = $this->person(SystemPermission::CreatePersonalAccessTokens);
        $inactive->forceFill(['netid_inactive' => true])->save();

        $deleted = User::factory()->api()->create();
        $deleted->delete();

        $this->assertRefused(AccessRefusal::AccountInactive, $inactive, CredentialOperation::Issue, CredentialKind::PersonalAccessToken, $inactive);
        $this->assertRefused(AccessRefusal::AccountInactive, $inactive, CredentialOperation::Use, CredentialKind::ConnectedApplication, $inactive);
        $this->assertRefused(AccessRefusal::AccountInactive, $deleted, CredentialOperation::Use, CredentialKind::ServiceClient, $deleted);

        // They can still see and give up what they have.
        $this->assertAllowed($inactive, CredentialOperation::See, CredentialKind::PersonalAccessToken, $inactive);
        $this->assertAllowed($inactive, CredentialOperation::Revoke, CredentialKind::PersonalAccessToken, $inactive);

        $administrator = $this->person(SystemPermission::ManageApiAccess);
        $this->assertRefused(AccessRefusal::AccountInactive, $administrator, CredentialOperation::Issue, CredentialKind::ServiceClient, $deleted);
        $this->assertRefused(AccessRefusal::AccountInactive, $administrator, CredentialOperation::Modify, CredentialKind::ServiceClient, $deleted);
        $this->assertAllowed($administrator, CredentialOperation::Revoke, CredentialKind::ServiceClient, $deleted);
    }

    public function test_administrators_need_manage_api_access(): void
    {
        $person = $this->person();
        $administrator = $this->person(SystemPermission::ManageApiAccess);
        $holder = $this->person();
        $apiUser = User::factory()->api()->create();

        $cases = [
            [CredentialOperation::See, CredentialKind::PersonalAccessToken, $holder],
            [CredentialOperation::Revoke, CredentialKind::PersonalAccessToken, $holder],
            [CredentialOperation::Revoke, CredentialKind::ConnectedApplication, $holder],
            [CredentialOperation::Issue, CredentialKind::ConnectedApplication, null],
            [CredentialOperation::Modify, CredentialKind::ConnectedApplication, null],
            [CredentialOperation::Revoke, CredentialKind::McpClient, null],
            [CredentialOperation::Issue, CredentialKind::ServiceClient, $apiUser],
            [CredentialOperation::Modify, CredentialKind::ServiceClient, $apiUser],
            [CredentialOperation::Revoke, CredentialKind::ServiceClient, $apiUser],
        ];

        foreach ($cases as [$operation, $kind, $holderOf]) {
            $this->assertRefused(AccessRefusal::MissingPermission, $person, $operation, $kind, $holderOf);
            $this->assertAllowed($administrator, $operation, $kind, $holderOf);
        }

        $this->assertRefused(AccessRefusal::WrongAccountType, $administrator, CredentialOperation::Issue, CredentialKind::ServiceClient, $holder);
    }

    /**
     * @return \Iterator<string, array{CredentialOperation, CredentialKind, bool}>
     */
    public static function operationsAdministratorsCannotDo(): \Iterator
    {
        yield 'use someone else\'s' => [CredentialOperation::Use, CredentialKind::PersonalAccessToken, true];
        yield 'issue someone\'s token' => [CredentialOperation::Issue, CredentialKind::PersonalAccessToken, true];
        yield 'connect someone\'s application' => [CredentialOperation::Issue, CredentialKind::ConnectedApplication, true];
        yield 'register an MCP client' => [CredentialOperation::Issue, CredentialKind::McpClient, false];
        yield 'modify an MCP client' => [CredentialOperation::Modify, CredentialKind::McpClient, false];
        yield 'issue a service client for no one' => [CredentialOperation::Issue, CredentialKind::ServiceClient, false];
    }

    #[DataProvider('operationsAdministratorsCannotDo')]
    public function test_operations_that_do_not_exist_are_unsupported(CredentialOperation $operation, CredentialKind $kind, bool $forHolder): void
    {
        $administrator = $this->person(SystemPermission::ManageAll);

        $this->assertRefused(AccessRefusal::Unsupported, $administrator, $operation, $kind, $forHolder ? $this->person() : null);
    }

    public function test_a_feature_that_is_off_refuses_all_but_an_administrator_seeing_and_revoking(): void
    {
        config(['mcp.enabled' => false]);
        $holder = $this->person(SystemPermission::UseMcp);
        $administrator = $this->person(SystemPermission::ManageApiAccess);

        foreach ([CredentialOperation::See, CredentialOperation::Issue, CredentialOperation::Revoke, CredentialOperation::Use] as $operation) {
            $this->assertRefused(AccessRefusal::FeatureOff, $holder, $operation, CredentialKind::McpClient, $holder);
        }

        $this->assertAllowed($administrator, CredentialOperation::See, CredentialKind::McpClient, null);
        $this->assertAllowed($administrator, CredentialOperation::Revoke, CredentialKind::McpClient, $holder);

        config(['api.enabled' => false]);
        $apiUser = User::factory()->api()->create();

        $this->assertRefused(AccessRefusal::FeatureOff, $administrator, CredentialOperation::Issue, CredentialKind::ServiceClient, $apiUser);
        $this->assertRefused(AccessRefusal::FeatureOff, $administrator, CredentialOperation::Modify, CredentialKind::ConnectedApplication, null);
        $this->assertRefused(AccessRefusal::FeatureOff, $apiUser, CredentialOperation::Use, CredentialKind::ServiceClient, $apiUser);
        $this->assertAllowed($administrator, CredentialOperation::Revoke, CredentialKind::ServiceClient, $apiUser);
    }

    public function test_nothing_is_issued_modified_or_revoked_while_impersonating(): void
    {
        $holder = $this->person(SystemPermission::CreatePersonalAccessTokens);
        $administrator = $this->person(SystemPermission::ManageApiAccess);
        $apiUser = User::factory()->api()->create();
        $this->impersonating();

        $this->assertRefused(AccessRefusal::Impersonating, $holder, CredentialOperation::Issue, CredentialKind::PersonalAccessToken, $holder);
        $this->assertRefused(AccessRefusal::Impersonating, $holder, CredentialOperation::Revoke, CredentialKind::ConnectedApplication, $holder);
        $this->assertRefused(AccessRefusal::Impersonating, $administrator, CredentialOperation::Modify, CredentialKind::ServiceClient, $apiUser);
        $this->assertRefused(AccessRefusal::Impersonating, $administrator, CredentialOperation::Revoke, CredentialKind::McpClient, null);

        // Seeing and using aren't changes.
        $this->assertAllowed($holder, CredentialOperation::See, CredentialKind::PersonalAccessToken, $holder);
        $this->assertAllowed($holder, CredentialOperation::Use, CredentialKind::PersonalAccessToken, $holder);
    }

    // Gate::before skips requests that carry a token; these rules must not.
    public function test_manage_all_satisfies_every_permission_even_with_a_token_attached(): void
    {
        $superAdministrator = $this->person(SystemPermission::ManageAll);
        $superAdministrator->withAccessToken(new AccessToken(['oauth_scopes' => [Registrar::OAUTH_SCOPE]]));
        $apiUser = User::factory()->api()->create();

        $this->assertAllowed($superAdministrator, CredentialOperation::Use, CredentialKind::McpClient, $superAdministrator);
        $this->assertAllowed($superAdministrator, CredentialOperation::Issue, CredentialKind::PersonalAccessToken, $superAdministrator);
        $this->assertAllowed($superAdministrator, CredentialOperation::Issue, CredentialKind::ServiceClient, $apiUser);
    }

    public function test_a_person_grants_only_the_scopes_they_hold(): void
    {
        $credentials = resolve(CredentialAccess::class);
        $viewer = $this->person(SystemPermission::ViewUsers, SystemPermission::UseMcp);
        $person = $this->person();

        $this->assertSame(['view-users'], array_keys($credentials->grantableScopes($viewer, CredentialKind::PersonalAccessToken)));
        $this->assertSame(['view-users'], array_keys($credentials->grantableScopes($viewer, CredentialKind::ConnectedApplication)));
        $this->assertSame([Registrar::OAUTH_SCOPE], array_keys($credentials->grantableScopes($viewer, CredentialKind::McpClient)));
        $this->assertSame([], $credentials->grantableScopes($person, CredentialKind::ConnectedApplication));
        $this->assertSame([], $credentials->grantableScopes($person, CredentialKind::McpClient));
        $this->assertSame([], $credentials->grantableScopes($viewer, CredentialKind::ServiceClient));
    }

    public function test_only_lasting_refusals_lead_to_revoking(): void
    {
        $this->assertSame(
            [AccessRefusal::MissingPermission, AccessRefusal::AccountInactive, AccessRefusal::WrongAccountType],
            array_values(array_filter(AccessRefusal::cases(), fn (AccessRefusal $reason): bool => $reason->isLasting())),
        );
    }

    public function test_a_refusal_answers_404_while_the_feature_is_off_and_403_otherwise(): void
    {
        AccessDecision::allow(CredentialKind::McpClient)->authorize();
        $this->assertSame('', AccessDecision::allow(CredentialKind::McpClient)->message());

        $expected = [
            [AccessRefusal::FeatureOff, 404, 'MCP clients are turned off.'],
            [AccessRefusal::Unsupported, 403, "That can't be done with MCP clients."],
            [AccessRefusal::Impersonating, 403, "MCP clients can't be changed while impersonating someone."],
            [AccessRefusal::MissingPermission, 403, "You don't have permission to do that with MCP clients."],
            [AccessRefusal::AccountInactive, 403, "The account is deactivated, so it can't use MCP clients."],
            [AccessRefusal::WrongAccountType, 403, "This kind of account can't hold MCP clients."],
        ];

        foreach ($expected as [$reason, $status, $message]) {
            try {
                AccessDecision::refuse($reason, CredentialKind::McpClient)->authorize();
                $this->fail("{$reason->name} didn't throw.");
            } catch (AuthorizationException $e) {
                $this->assertSame($message, $e->getMessage());
                $this->assertSame($reason === AccessRefusal::FeatureOff ? 404 : null, $e->status());
            }
        }
    }

    private function person(SystemPermission ...$permissions): User
    {
        $user = User::factory()->affiliate()->create();

        if ($permissions !== []) {
            $user->givePermissionTo(...$permissions);
        }

        return $user;
    }

    private function impersonating(): void
    {
        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $this->app->instance('impersonate', $impersonate);
    }

    private function assertAllowed(User $actor, CredentialOperation $operation, CredentialKind $kind, ?User $holder): void
    {
        $decision = resolve(CredentialAccess::class)->decide($actor, $operation, $kind, $holder);

        $this->assertTrue($decision->allowed, "{$operation->name} {$kind->name} was refused: {$decision->reason?->name}");
    }

    private function assertRefused(AccessRefusal $reason, User $actor, CredentialOperation $operation, CredentialKind $kind, ?User $holder): void
    {
        $decision = resolve(CredentialAccess::class)->decide($actor, $operation, $kind, $holder);

        $this->assertFalse($decision->allowed, "{$operation->name} {$kind->name} was allowed.");
        $this->assertSame($reason, $decision->reason, "{$operation->name} {$kind->name}");
    }
}
