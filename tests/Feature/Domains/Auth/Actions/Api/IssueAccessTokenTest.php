<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Api;

use App\Domains\Auth\Actions\Api\IssueAccessToken;
use App\Domains\Auth\Models\AccessToken;
use App\Domains\User\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(IssueAccessToken::class)]
class IssueAccessTokenTest extends TestCase
{
    public function test_issues_token_for_api_user(): void
    {
        Carbon::setTestNow(now());

        $user = User::factory()->api()->create();

        $action = new IssueAccessToken();

        [$token, $accessToken] = $action(
            user: $user,
            name: 'Test Token',
            expiresAt: now()->addDays(7),
            allowedIps: ['192.168.1.1']
        );

        $this->assertSame('Test Token', $accessToken->name);
        $this->assertSame(mb_substr($token, 0, 5), $accessToken->token_prefix);
        $this->assertSame(AccessToken::hashFromPlain($token), $accessToken->token_hash);
        $this->assertEquals(now()->addDays(7)->startOfSecond(), $accessToken->expires_at->startOfSecond());
        $this->assertSame(['192.168.1.1'], $accessToken->allowed_ips);
        $this->assertTrue($user->access_tokens->contains($accessToken));
    }

    public function test_fails_if_user_is_not_api_user(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tokens can only be issued for API users.');

        $user = User::factory()->affiliate()->create();

        $action = new IssueAccessToken();
        $action($user, 'Test');
    }

    public function test_defaults_are_used_when_optional_parameters_are_omitted(): void
    {
        Carbon::setTestNow(now());

        $user = User::factory()->api()->create();

        $action = new IssueAccessToken();

        [$token, $accessToken] = $action(
            user: $user,
            name: 'Default Token'
        );

        $this->assertSame('Default Token', $accessToken->name);
        $this->assertNull($accessToken->expires_at);
        $this->assertNull($accessToken->allowed_ips);
    }
}
