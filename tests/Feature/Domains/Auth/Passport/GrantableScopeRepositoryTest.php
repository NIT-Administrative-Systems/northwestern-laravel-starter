<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Passport;

use App\Domains\Auth\Passport\GrantableScopeRepository;
use Illuminate\Support\Str;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\ScopeRepository;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * The narrowing an authorization code gets is tested through the whole flow in
 * AuthorizationCodeFlowTest; these are the cases the flow can't reach.
 */
#[CoversClass(GrantableScopeRepository::class)]
final class GrantableScopeRepositoryTest extends TestCase
{
    public function test_it_replaces_passports_scope_repository(): void
    {
        $this->assertInstanceOf(GrantableScopeRepository::class, resolve(ScopeRepository::class));
    }

    // Nobody to grant on behalf of, so nothing is granted.
    public function test_an_authorization_code_for_an_unknown_person_or_client_carries_no_scopes(): void
    {
        $scopes = resolve(ScopeRepository::class)->finalizeScopes(
            [new Scope('view-users')],
            'authorization_code',
            new Client((string) Str::uuid(), 'Gone', []),
            '999999',
        );

        $this->assertSame([], $scopes);
    }

    // Only a person's authorization is narrowed; other grants keep Passport's rules.
    public function test_other_grants_keep_passports_rules(): void
    {
        $scopes = resolve(ScopeRepository::class)->finalizeScopes(
            [new Scope('view-users')],
            'client_credentials',
            new Client((string) Str::uuid(), 'Sync', []),
        );

        $this->assertSame(['view-users'], array_map(fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(), $scopes));
    }
}
