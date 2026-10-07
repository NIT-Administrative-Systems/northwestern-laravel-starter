<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\Concerns;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\AccessRefusal;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\ValueObjects\AccessDecision;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\User;
use Illuminate\Auth\AuthenticationException;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

#[CoversTrait(AuthorizesCredentials::class)]
final class AuthorizesCredentialsTest extends TestCase
{
    public function test_it_asks_about_the_signed_in_person(): void
    {
        $holder = User::factory()->affiliate()->create();
        $holder->givePermissionTo(SystemPermission::CreatePersonalAccessTokens);
        $this->actingAs($holder);

        $this->assertTrue(Surface::allows(CredentialOperation::Issue, CredentialKind::PersonalAccessToken, $holder));
        $this->assertTrue(Surface::actingAs()->is($holder));

        $this->actingAs(User::factory()->affiliate()->create());

        $this->assertSame(AccessRefusal::MissingPermission, Surface::decision(CredentialOperation::Revoke, CredentialKind::PersonalAccessToken, $holder)->reason);
    }

    // A null actor is the system, which may do anything; nobody signed in may do nothing.
    public function test_nobody_signed_in_is_refused_not_treated_as_the_system(): void
    {
        $this->assertFalse(Surface::allows(CredentialOperation::Issue, CredentialKind::ServiceClient));

        $this->expectException(AuthenticationException::class);
        Surface::actingAs();
    }
}

/**
 * A page, resource or action that uses the trait.
 */
final class Surface
{
    use AuthorizesCredentials;

    public static function decision(CredentialOperation $operation, CredentialKind $kind, ?User $holder = null): AccessDecision
    {
        return self::credentialAccess($operation, $kind, $holder);
    }

    public static function allows(CredentialOperation $operation, CredentialKind $kind, ?User $holder = null): bool
    {
        return self::allowsCredential($operation, $kind, $holder);
    }

    public static function actingAs(): User
    {
        return self::actingUser();
    }
}
