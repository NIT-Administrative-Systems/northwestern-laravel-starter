<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Models;

use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Models\OAuthToken;
use App\Domains\User\Models\User;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\IssuesPersonalAccessTokens;
use Tests\Concerns\IssuesServiceClientTokens;
use Tests\TestCase;

#[CoversClass(OAuthToken::class)]
final class OAuthTokenTest extends TestCase
{
    use IssuesPersonalAccessTokens, IssuesServiceClientTokens;

    public function test_passport_uses_it(): void
    {
        $this->assertSame(OAuthToken::class, Passport::tokenModel());
    }

    public function test_the_personal_scope_excludes_service_client_tokens(): void
    {
        [, $personal] = $this->personalAccessToken(User::factory()->create());
        $this->serviceClientToken(User::factory()->api()->create());

        $this->assertSame([$personal->getKey()], OAuthToken::query()->personal()->pluck('id')->all());
    }

    public function test_status_follows_revocation_and_expiry(): void
    {
        [, $token] = $this->personalAccessToken(User::factory()->create());
        $this->assertSame(CredentialStatus::Active, $token->status);
        $this->assertSame([$token->getKey()], OAuthToken::query()->personal()->active()->pluck('id')->all());

        $this->travel(31)->days();
        $this->assertSame(CredentialStatus::Expired, $token->status);
        $this->assertSame([], OAuthToken::query()->personal()->active()->pluck('id')->all());

        $token->revoke();
        $this->assertSame(CredentialStatus::Revoked, $token->fresh()?->status);
    }
}
