<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Personal;

use App\Domains\Auth\Actions\Personal\CreatePersonalAccessToken;
use App\Domains\Auth\Actions\Personal\RevokePersonalAccessToken;
use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Enums\TokenExpiration;
use App\Domains\Auth\Models\OAuthToken;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RevokePersonalAccessToken::class)]
final class RevokePersonalAccessTokenTest extends TestCase
{
    public function test_a_person_revokes_their_own_token(): void
    {
        [$user, $accessToken, $token] = $this->token();

        resolve(RevokePersonalAccessToken::class)($token, $user);

        $this->assertSame(CredentialStatus::Revoked, $token->fresh()?->status);
        $this->withToken($accessToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertFalse(Audit::query()->where('event', 'personal_access_token_revoked')->exists());
    }

    public function test_an_administrator_revoking_someone_elses_token_is_audited_on_them(): void
    {
        [$user, , $token] = $this->token();

        resolve(RevokePersonalAccessToken::class)($token, User::factory()->create());

        $audit = Audit::query()->where('event', 'personal_access_token_revoked')->sole();
        $this->assertSame($user->getKey(), $audit->auditable_id);
        $this->assertSame($token->getKey(), $audit->new_values['token_id']);
    }

    public function test_it_is_refused_while_impersonating(): void
    {
        [$user, , $token] = $this->token();

        $impersonate = Mockery::mock();
        $impersonate->shouldReceive('isImpersonating')->andReturn(true);
        $impersonate->shouldReceive('getImpersonatorId')->andReturn(null);
        $this->app->instance('impersonate', $impersonate);

        $this->expectException(AuthorizationException::class);

        resolve(RevokePersonalAccessToken::class)($token, $user);
    }

    /**
     * @return array{0: User, 1: string, 2: OAuthToken}
     */
    private function token(): array
    {
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::CreatePersonalAccessTokens);

        [$accessToken, $token] = resolve(CreatePersonalAccessToken::class)($user, 'Script', [], TokenExpiration::OneMonth);

        return [$user, $accessToken, $token];
    }
}
