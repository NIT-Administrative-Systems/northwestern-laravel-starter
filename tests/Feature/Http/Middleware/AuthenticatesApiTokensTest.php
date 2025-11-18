<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Domains\Core\Enums\ApiRequestFailureEnum;
use App\Domains\Core\ValueObjects\ApiRequestContext;
use App\Domains\User\Models\ApiToken;
use App\Domains\User\Models\User;
use App\Http\Middleware\AuthenticatesApiTokens;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(AuthenticatesApiTokens::class)]
class AuthenticatesApiTokensTest extends TestCase
{
    private string $endpoint = '/api/test';

    private CarbonImmutable $testTime;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(AuthenticatesApiTokens::class)->get($this->endpoint, function () {
            return response()->json(['user_id' => Auth::id()]);
        });

        $this->testTime = CarbonImmutable::parse('2025-05-16 09:00');
        CarbonImmutable::setTestNow($this->testTime);

        Context::flush();
    }

    protected function tearDown(): void
    {
        Context::flush();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function bearerHeader(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * @return array{0: string, 1: ApiToken} Tuple of plain token and the created {@see ApiToken}
     */
    private function issueToken(User $user, array $overrides = []): array
    {
        $plainToken = $overrides['plain_token'] ?? Str::uuid()->toString();
        unset($overrides['plain_token']);

        $attributes = array_merge([
            'token_prefix' => mb_substr((string) $plainToken, 0, 5),
            'token_hash' => ApiToken::hashFromPlain($plainToken),
            'valid_from' => $this->testTime->subMinute(),
            'valid_to' => null,
            'allowed_ips' => null,
        ], $overrides);

        $token = ApiToken::factory()
            ->for($user)
            ->create($attributes);

        return [$plainToken, $token];
    }

    #[DataProvider('malformedAuthHeaderProvider')]
    public function test_malformed_bearer_auth_header_is_rejected(?string $header, ApiRequestFailureEnum $failureReason): void
    {
        $headers = [];
        if ($header !== null) {
            $headers['Authorization'] = $header;
        }

        $this->getJson($this->endpoint, $headers)
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="' . config('auth.api.auth_realm') . '"')
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Authentication failed',
                'instance' => '/api/test',
            ]);

        $this->assertSame(
            $failureReason->value,
            Context::get(ApiRequestContext::FAILURE_REASON),
        );
    }

    public static function malformedAuthHeaderProvider(): array
    {
        return [
            'no header' => [null, ApiRequestFailureEnum::INVALID_HEADER_FORMAT],
            'empty header' => ['', ApiRequestFailureEnum::INVALID_HEADER_FORMAT],
            'wrong scheme' => ['Basic abc123', ApiRequestFailureEnum::INVALID_HEADER_FORMAT],
            'bearer with no token' => ['Bearer', ApiRequestFailureEnum::INVALID_HEADER_FORMAT],
            'bearer with space only' => ['Bearer   ', ApiRequestFailureEnum::MISSING_CREDENTIALS],
        ];
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->getJson(
            $this->endpoint,
            $this->bearerHeader('non-existent-token')
        )
            ->assertUnauthorized()
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Authentication failed',
                'instance' => $this->endpoint,
            ]);

        $this->assertNotNull(Context::get(ApiRequestContext::TRACE_ID));
        $this->assertNull(Context::get(ApiRequestContext::USER_ID));
        $this->assertNull(Context::get(ApiRequestContext::TOKEN_ID));
        $this->assertSame(
            ApiRequestFailureEnum::TOKEN_INVALID_OR_EXPIRED->value,
            Context::get(ApiRequestContext::FAILURE_REASON),
        );
    }

    public function test_soft_deleted_api_user_is_rejected(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken] = $this->issueToken($user);

        $user->delete();

        $this->getJson(
            $this->endpoint,
            $this->bearerHeader($plainToken)
        )
            ->assertUnauthorized()
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Authentication failed',
                'instance' => '/api/test',
            ]);
    }

    public function test_non_api_user_is_rejected(): void
    {
        $user = User::factory()->create();
        [$plainToken] = $this->issueToken($user);

        $this->getJson(
            $this->endpoint,
            $this->bearerHeader($plainToken)
        )->assertUnauthorized()
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Authentication failed',
                'instance' => '/api/test',
            ]);
    }

    #[DataProvider('invalidTokenProvider')]
    public function test_invalid_tokens_are_rejected(array $tokenData): void
    {
        $user = User::factory()->api()->create();
        [$plainToken] = $this->issueToken($user, $tokenData);

        $response = $this->getJson(
            $this->endpoint,
            $this->bearerHeader($plainToken)
        );

        $response->assertUnauthorized()
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Authentication failed',
                'instance' => '/api/test',
            ]);

        $this->assertDatabaseMissing(ApiToken::class, [
            'user_id' => $user->id,
            'last_used_at' => $this->testTime,
        ]);
    }

    public static function invalidTokenProvider(): array
    {
        $now = CarbonImmutable::parse('2025-05-16 09:00');

        return [
            'pending token' => [
                [
                    'valid_from' => $now->addHour(),
                    'valid_to' => null,
                ],
            ],
            'expired token' => [
                [
                    'valid_from' => $now->subDays(2),
                    'valid_to' => $now->subDay(),
                ],
            ],
            'revoked token' => [
                [
                    'revoked_at' => $now->subHour(),
                ],
            ],
        ];
    }

    public function test_active_token_with_open_end_date_authenticates_and_updates_last_used(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken, $token] = $this->issueToken($user);

        $response = $this->getJson($this->endpoint, $this->bearerHeader($plainToken));

        $response->assertOk()
            ->assertJson(['user_id' => $user->id]);

        $this->assertDatabaseHas(ApiToken::class, [
            'id' => $token->id,
            'last_used_at' => $this->testTime,
            'usage_count' => 1,
        ]);

        $this->assertNotNull(Context::get(ApiRequestContext::TRACE_ID));
        $this->assertSame($user->id, Context::get(ApiRequestContext::USER_ID));
        $this->assertSame($token->id, Context::get(ApiRequestContext::TOKEN_ID));
        $this->assertNull(Context::get(ApiRequestContext::FAILURE_REASON));
    }

    public function test_active_token_with_future_end_date_authenticates(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken, $token] = $this->issueToken($user, [
            'valid_to' => $this->testTime->addDays(10),
        ]);

        $this->getJson(
            $this->endpoint,
            $this->bearerHeader($plainToken)
        )
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);

        $this->assertDatabaseHas(ApiToken::class, [
            'id' => $token->id,
            'last_used_at' => $this->testTime,
            'usage_count' => 1,
        ]);
    }

    public function test_usage_count_is_incremented(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken, $token] = $this->issueToken($user);

        $this->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);

        $this->assertDatabaseHas(ApiToken::class, [
            'id' => $token->id,
            'usage_count' => 1,
        ]);

        CarbonImmutable::setTestNow($this->testTime = $this->testTime->addSecond());

        $this->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);

        $this->assertDatabaseHas(ApiToken::class, [
            'id' => $token->id,
            'usage_count' => 2,
        ]);
    }

    public function test_token_with_no_ip_restrictions_allows_all_ips(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken] = $this->issueToken($user, ['allowed_ips' => null]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);
    }

    public function test_token_with_exact_ipv4_match_allows_request(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken] = $this->issueToken($user, [
            'allowed_ips' => ['192.168.1.100'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);
    }

    public function test_token_with_ipv4_mismatch_rejects_request(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken, $token] = $this->issueToken($user, [
            'allowed_ips' => ['192.168.1.100'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.101'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertUnauthorized()
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Authentication failed',
                'instance' => '/api/test',
            ]);

        $this->assertNotNull(Context::get(ApiRequestContext::TRACE_ID));
        $this->assertSame($user->id, Context::get(ApiRequestContext::USER_ID));
        $this->assertSame($token->id, Context::get(ApiRequestContext::TOKEN_ID));
        $this->assertSame(
            ApiRequestFailureEnum::IP_DENIED->value,
            Context::get(ApiRequestContext::FAILURE_REASON),
        );
    }

    public function test_token_with_ipv4_cidr_range_match_allows_request(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken] = $this->issueToken($user, [
            'allowed_ips' => ['192.168.1.0/24'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.200'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);
    }

    public function test_token_with_ipv4_cidr_range_mismatch_rejects_request(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken] = $this->issueToken($user, [
            'allowed_ips' => ['192.168.1.0/24'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.2.100'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertUnauthorized()
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Authentication failed',
                'instance' => '/api/test',
            ]);
    }

    public function test_token_with_large_cidr_range_allows_matching_ips(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken] = $this->issueToken($user, [
            'allowed_ips' => ['10.0.0.0/8'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.123.45.67'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);
    }

    public function test_token_with_multiple_allowed_ips_matches_any(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken] = $this->issueToken($user, [
            'allowed_ips' => ['192.168.1.100', '203.0.113.50', '198.51.100.0/24'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.123'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);

        $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertUnauthorized()
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Authentication failed',
                'instance' => '/api/test',
            ]);
    }

    public function test_token_with_ipv6_exact_match_allows_request(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken] = $this->issueToken($user, [
            'allowed_ips' => ['2001:db8::1'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::1'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);
    }

    public function test_token_with_ipv6_cidr_range_allows_matching_ips(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken] = $this->issueToken($user, [
            'allowed_ips' => ['2001:db8::/32'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:0:0:0:0:0:1'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);
    }

    public function test_token_with_mixed_ipv4_and_ipv6_restrictions(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken] = $this->issueToken($user, [
            'allowed_ips' => ['192.168.1.0/24', '2001:db8::/32', '203.0.113.42'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::cafe'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);

        $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertUnauthorized()
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Authentication failed',
                'instance' => '/api/test',
            ]);
    }

    public function test_ip_restriction_does_not_prevent_last_used_update_on_failure(): void
    {
        $user = User::factory()->api()->create();
        [$plainToken, $token] = $this->issueToken($user, [
            'allowed_ips' => ['192.168.1.100'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->getJson($this->endpoint, $this->bearerHeader($plainToken))
            ->assertUnauthorized();

        $this->assertDatabaseMissing(ApiToken::class, [
            'id' => $token->id,
            'last_used_at' => $this->testTime,
        ]);
    }
}
