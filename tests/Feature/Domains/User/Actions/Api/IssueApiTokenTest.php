<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions\Api;

use App\Domains\User\Actions\Api\IssueApiToken;
use App\Domains\User\Models\ApiToken;
use App\Domains\User\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(IssueApiToken::class)]
class IssueApiTokenTest extends TestCase
{
    public function test_issues_token_for_api_user(): void
    {
        Carbon::setTestNow(now());

        $user = User::factory()->api()->create();

        $action = new IssueApiToken();

        [$token, $apiToken] = $action(
            user: $user,
            validFrom: now()->addHour(),
            validTo: now()->addDays(7),
            allowedIps: ['192.168.1.1']
        );

        $this->assertSame(mb_substr($token, 0, 5), $apiToken->token_prefix);
        $this->assertSame(ApiToken::hashFromPlain($token), $apiToken->token_hash);
        $this->assertEquals(now()->addHour()->startOfSecond(), $apiToken->valid_from->startOfSecond());
        $this->assertEquals(now()->addDays(7)->startOfSecond(), $apiToken->valid_to->startOfSecond());
        $this->assertSame(['192.168.1.1'], $apiToken->allowed_ips);
        $this->assertTrue($user->api_tokens->contains($apiToken));
    }

    public function test_fails_if_user_is_not_api_user(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tokens can only be issued for API users.');

        $user = User::factory()->affiliate()->create();

        $action = new IssueApiToken();
        $action($user);
    }

    public function test_defaults_are_used_when_optional_parameters_are_omitted(): void
    {
        Carbon::setTestNow(now());

        $user = User::factory()->api()->create();

        $action = new IssueApiToken();

        [$token, $apiToken] = $action($user);

        $this->assertEquals(now()->startOfSecond(), $apiToken->valid_from->startOfSecond());
        $this->assertNull($apiToken->valid_to);
        $this->assertNull($apiToken->allowed_ips);
    }
}
