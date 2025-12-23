<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions\Api;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\Auth\Models\AccessToken;
use App\Domains\User\Actions\Api\CreateApiUser;
use App\Domains\User\Enums\AffiliationEnum;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CreateApiUser::class)]
class CreateApiUserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $now = CarbonImmutable::parse('2025-12-03 00:00:00');

        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);
    }

    public function test_it_creates_api_user_with_token(): void
    {
        $action = new CreateApiUser();

        [$user, $token] = $action(
            username: 'api-service',
            firstName: 'My Service',
            tokenName: 'Production Server',
            description: 'Test description',
            email: 'api@example.com',
            expiresAt: now()->addDays(7),
            allowedIps: ['127.0.0.1']
        );

        $this->assertSame('api-service', $user->username);
        $this->assertSame(AuthTypeEnum::API, $user->auth_type);
        $this->assertSame(AffiliationEnum::OTHER, $user->primary_affiliation);
        $this->assertSame('My Service', $user->first_name);
        $this->assertSame('API', $user->last_name);
        $this->assertSame('api@example.com', $user->email);
        $this->assertSame('Test description', $user->description);

        $accessToken = $user->access_tokens->first();
        $this->assertInstanceOf(AccessToken::class, $accessToken);
        $this->assertSame('Production Server', $accessToken->name);
        $this->assertSame(mb_substr($token, 0, 5), $accessToken->token_prefix);
        $this->assertSame(AccessToken::hashFromPlain($token), $accessToken->token_hash);
        $this->assertEquals(now()->addDays(7)->startOfSecond(), $accessToken->expires_at->startOfSecond());
        $this->assertSame(['127.0.0.1'], $accessToken->allowed_ips);
    }

    public function test_it_uses_defaults_when_optional_arguments_are_null(): void
    {
        $action = new CreateApiUser();

        [$user, $token] = $action(
            username: 'api-default',
            firstName: 'Default Service',
            tokenName: 'Default Token'
        );

        $this->assertSame('api-default', $user->username);
        $this->assertSame('Default Service', $user->first_name);
        $this->assertSame('API', $user->last_name);
        $this->assertNull($user->description);
        $this->assertNull($user->email);

        $accessToken = $user->access_tokens->first();
        $this->assertNull($accessToken->expires_at);
        $this->assertNull($accessToken->allowed_ips);
    }
}
