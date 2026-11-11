<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\Http\Middleware;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Http\Middleware\LogsApiRequests;
use App\Domains\Api\Models\ApiRequestLog;
use App\Domains\User\Models\User;
use App\Mcp\Servers\AppServer;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Northwestern\SysDev\Chassis\Enums\ApiPrincipalType;
use Northwestern\SysDev\Chassis\Enums\ApiRequestFailure;
use Northwestern\SysDev\Chassis\Enums\OAuthGrantType;
use Northwestern\SysDev\Chassis\ValueObjects\ApiRequestContext;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\Fixtures\Mcp\EchoServer;
use Tests\TestCase;

#[CoversClass(LogsApiRequests::class)]
final class LogsApiRequestsTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    private string $endpoint = '/api/test';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(LogsApiRequests::class)->get($this->endpoint, fn () => response()->json(['success' => true]));

        config()->set('api.request_logging.enabled', true);
        config()->set('api.request_logging.sampling.enabled', false);

        Context::flush();
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    public function test_nothing_is_logged_while_logging_is_off(): void
    {
        config()->set('api.request_logging.enabled', false);

        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        $this->getJson($this->endpoint)->assertOk();

        $this->assertDatabaseCount(ApiRequestLog::class, 0);
    }

    public function test_a_request_with_no_user_or_failure_is_not_logged(): void
    {
        // Neither a user nor a failure reason is in the context.
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        $this->getJson($this->endpoint)->assertOk();

        $this->assertDatabaseCount(ApiRequestLog::class, 0);
    }

    public function test_an_authenticated_request_is_logged(): void
    {
        $user = User::factory()->api()->create();
        $clientId = Str::uuid()->toString();
        $tokenId = Str::random(80);
        $traceId = Str::uuid()->toString();

        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::PRINCIPAL_TYPE, ApiPrincipalType::Client->value);
        Context::add(ApiRequestContext::OAUTH_CLIENT_ID, $clientId);
        Context::add(ApiRequestContext::OAUTH_TOKEN_ID, $tokenId);
        Context::add(ApiRequestContext::OAUTH_GRANT_TYPE, OAuthGrantType::ClientCredentials->value);
        Context::add(ApiRequestContext::TRACE_ID, $traceId);

        $this->getJson($this->endpoint, ['User-Agent' => 'TestAgent/1.0'])
            ->assertOk();

        $this->assertDatabaseHas(ApiRequestLog::class, [
            'trace_id' => $traceId,
            'user_id' => $user->id,
            'principal_type' => 'client',
            'oauth_client_id' => $clientId,
            'token_id' => $tokenId,
            'grant_type' => 'client_credentials',
            'method' => 'GET',
            'path' => 'api/test',
            'status_code' => 200,
            'user_agent' => 'TestAgent/1.0',
            'failure_reason' => null,
        ]);

        $log = ApiRequestLog::first();
        $this->assertNotNull($log->duration_ms);
        $this->assertGreaterThanOrEqual(0, $log->duration_ms);
        $this->assertNotNull($log->response_bytes);
        $this->assertGreaterThan(0, $log->response_bytes);
    }

    public function test_a_request_that_failed_authentication_is_logged(): void
    {
        $user = User::factory()->api()->create();
        $traceId = Str::uuid()->toString();

        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, $traceId);
        Context::add(ApiRequestContext::FAILURE_REASON, ApiRequestFailure::IpDenied->value);

        Route::middleware(LogsApiRequests::class)->get('/api/forbidden', fn () => response()->json(['error' => 'Forbidden'], 403));

        $this->getJson('/api/forbidden')->assertForbidden();

        $this->assertDatabaseHas(ApiRequestLog::class, [
            'trace_id' => $traceId,
            'user_id' => $user->id,
            'status_code' => 403,
            'failure_reason' => ApiRequestFailure::IpDenied->value,
        ]);
    }

    public function test_it_records_the_route_name(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        Route::middleware(LogsApiRequests::class)
            ->get('/api/named', fn () => response()->json(['ok' => true]))
            ->name('api.named.route');

        $this->getJson('/api/named')->assertOk();

        $this->assertDatabaseHas(ApiRequestLog::class, [
            'route_name' => 'api.named.route',
        ]);
    }

    public function test_an_unnamed_route_records_no_route_name(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        $this->getJson($this->endpoint)->assertOk();

        $log = ApiRequestLog::first();
        $this->assertNull($log->route_name);
    }

    public function test_without_sampling_every_successful_request_is_logged(): void
    {
        config()->set('api.request_logging.sampling.enabled', false);

        $user = User::factory()->api()->create();

        for ($i = 0; $i < 3; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

            $this->getJson($this->endpoint)->assertOk();
        }

        $this->assertDatabaseCount(ApiRequestLog::class, 3);
    }

    public function test_a_zero_sample_rate_logs_no_successful_requests(): void
    {
        config()->set('api.request_logging.sampling.enabled', true);
        config()->set('api.request_logging.sampling.rate', 0.0);

        $user = User::factory()->api()->create();

        for ($i = 0; $i < 3; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

            $this->getJson($this->endpoint)->assertOk();
        }

        $this->assertDatabaseCount(ApiRequestLog::class, 0);
    }

    public function test_a_full_sample_rate_logs_every_successful_request(): void
    {
        config()->set('api.request_logging.sampling.enabled', true);
        config()->set('api.request_logging.sampling.rate', 1.0);

        $user = User::factory()->api()->create();

        for ($i = 0; $i < 3; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

            $this->getJson($this->endpoint)->assertOk();
        }

        $this->assertDatabaseCount(ApiRequestLog::class, 3);
    }

    public function test_errors_are_logged_whatever_the_sample_rate(): void
    {
        config()->set('api.request_logging.sampling.enabled', true);
        config()->set('api.request_logging.sampling.rate', 0.0);

        $user = User::factory()->api()->create();

        Route::middleware(LogsApiRequests::class)->get('/api/error', fn () => response()->json(['error' => 'Not Found'], 404));

        for ($i = 0; $i < 3; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

            $this->getJson('/api/error')->assertNotFound();
        }

        // Every error is logged, even at a sample rate of zero.
        $this->assertDatabaseCount(ApiRequestLog::class, 3);
    }

    public function test_failures_are_logged_whatever_the_sample_rate(): void
    {
        config()->set('api.request_logging.sampling.enabled', true);
        config()->set('api.request_logging.sampling.rate', 0.0);

        $user = User::factory()->api()->create();

        for ($i = 0; $i < 3; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());
            Context::add(ApiRequestContext::FAILURE_REASON, ApiRequestFailure::TokenInvalidOrExpired->value);

            $this->getJson($this->endpoint)->assertOk();
        }

        // Every failure is logged, even at a sample rate of zero.
        $this->assertDatabaseCount(ApiRequestLog::class, 3);
    }

    public function test_a_half_sample_rate_logs_about_half_of_successful_requests(): void
    {
        config()->set('api.request_logging.sampling.enabled', true);
        config()->set('api.request_logging.sampling.rate', 0.5);

        $user = User::factory()->api()->create();

        for ($i = 0; $i < 30; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

            $this->getJson($this->endpoint)->assertOk();
        }

        $logCount = ApiRequestLog::count();

        // About 15 are logged; the bounds are wide because sampling is random.
        $this->assertGreaterThan(3, $logCount, 'Expected at least ~10% of 30 requests to be logged');
        $this->assertLessThan(27, $logCount, 'Expected at most ~90% of 30 requests to be logged');
    }

    public function test_a_failure_to_log_does_not_break_the_request(): void
    {
        $user = User::factory()->api()->create();

        // A trace ID that isn't a UUID makes saving the log fail.
        app()->bind('test.set.context.with.error', fn () => function ($request, $next) use ($user) {
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, 'invalid-trace-id-format-to-cause-error');

            return $next($request);
        });

        Route::middleware(['test.set.context.with.error', LogsApiRequests::class])
            ->get('/api/db-error-test', fn () => response()->json(['ok' => true]));

        // Logging fails, but the request still succeeds.
        $this->getJson('/api/db-error-test')->assertOk();
    }

    public function test_it_records_the_ip_address(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        $this->getJson($this->endpoint, ['X-Forwarded-For' => '192.168.1.100'])
            ->assertOk();

        $log = ApiRequestLog::first();
        // The forwarded address or 127.0.0.1, depending on the trusted proxies.
        $this->assertNotEmpty($log->ip_address);
    }

    public function test_every_log_has_an_ip_address(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        $this->getJson($this->endpoint)->assertOk();

        $log = ApiRequestLog::first();
        // In tests this is 127.0.0.1; the middleware records a missing IP as 'unknown'.
        $this->assertNotNull($log->ip_address);
    }

    // A failed tool call or a JSON-RPC error still answers 200; only the body tells.
    public function test_mcp_requests_record_the_method_tool_and_outcome(): void
    {
        config(['mcp.enabled' => true]);
        $this->app->bind(AppServer::class, EchoServer::class);
        $user = User::factory()->create();
        $user->givePermissionTo(SystemPermission::UseMcp);
        $this->actingAs($user);
        $token = $this->mcpToken();

        $this->mcp($token, 'tools/call', ['name' => 'echo', 'arguments' => ['text' => 'hello']])->assertOk();
        $this->mcp($token, 'tools/call', ['name' => 'echo', 'arguments' => ['text' => 'h']])->assertOk();
        $this->mcp($token, 'resources/read', ['uri' => 'file:///missing'])->assertBadRequest();
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], ['Accept' => 'application/json, text/event-stream', 'Authorization' => "Bearer {$token}"])->assertAccepted();

        $this->assertSame([
            ['tools/call', 'echo', 'ok'],
            ['tools/call', 'echo', 'tool_error'],
            ['resources/read', null, 'error'],
            ['notifications/initialized', null, null],
        ], ApiRequestLog::query()->orderBy('id')->get()->map(fn (ApiRequestLog $log): array => [$log->mcp_method, $log->mcp_tool, $log->mcp_outcome])->all());
    }

    public function test_an_mcp_response_without_a_json_rpc_body_records_no_outcome(): void
    {
        Context::add(ApiRequestContext::USER_ID, User::factory()->create()->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());
        Route::middleware(LogsApiRequests::class)->post('/api/test-mcp-text', fn () => response()->json(['message' => 'Unauthenticated.'], 401))->name('mcp.server');
        Route::middleware(LogsApiRequests::class)->post('/api/test-mcp-stream', fn () => response()->stream(function (): void {
            echo 'data: {}';
        }))->name('mcp.server');

        $this->postJson('/api/test-mcp-text', ['method' => 'ping'])->assertUnauthorized();
        $this->postJson('/api/test-mcp-stream', ['method' => 'ping'])->assertOk();

        $this->assertSame([null, null], ApiRequestLog::query()->orderBy('id')->pluck('mcp_outcome')->all());
        $this->assertSame(['ping', 'ping'], ApiRequestLog::query()->orderBy('id')->pluck('mcp_method')->all());
    }

    public function test_a_streamed_response_records_no_response_size(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        Route::middleware(LogsApiRequests::class)->get('/api/stream', fn () => response()->stream(function () {
            echo 'streaming data';
        }));

        $this->get('/api/stream')->assertOk();

        $log = ApiRequestLog::first();
        $this->assertNull($log->response_bytes);
    }

    public function test_the_response_size_comes_from_its_content_length(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        Route::middleware(LogsApiRequests::class)->get('/api/sized', fn () => response()->json(['data' => 'test'])
            ->header('Content-Length', '1234'));

        $this->getJson('/api/sized')->assertOk();

        $log = ApiRequestLog::first();
        $this->assertSame(1234, $log->response_bytes);
    }

    public function test_the_request_size_comes_from_its_content_length(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        Route::middleware(LogsApiRequests::class)->post('/api/post-test', fn () => response()->json(['ok' => true]));

        $this->postJson('/api/post-test', ['data' => 'test'], ['Content-Length' => '512'])
            ->assertOk();

        $log = ApiRequestLog::first();
        $this->assertSame(512, $log->request_bytes);
    }

    public function test_a_request_without_a_content_length_records_no_size(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        Route::middleware(LogsApiRequests::class)->get('/api/no-body', fn () => response()->json(['ok' => true]));

        $this->get('/api/no-body', ['Accept' => 'application/json'])->assertOk();

        $log = ApiRequestLog::first();
        $this->assertNull($log->request_bytes);
    }

    public function test_it_records_how_long_the_request_took(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        Route::middleware(LogsApiRequests::class)->get('/api/slow', function () {
            \Illuminate\Support\Sleep::usleep(10000); // 10ms delay

            return response()->json(['ok' => true]);
        });

        $this->getJson('/api/slow')->assertOk();

        $log = ApiRequestLog::first();
        $this->assertGreaterThanOrEqual(10, $log->duration_ms);
        $this->assertLessThan(1000, $log->duration_ms); // Should be less than 1 second
    }

    public function test_it_records_everything_the_request_context_holds(): void
    {
        $user = User::factory()->api()->create();
        $clientId = Str::uuid()->toString();
        $tokenId = Str::random(80);
        $traceId = Str::uuid()->toString();

        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::PRINCIPAL_TYPE, ApiPrincipalType::Client->value);
        Context::add(ApiRequestContext::OAUTH_CLIENT_ID, $clientId);
        Context::add(ApiRequestContext::OAUTH_TOKEN_ID, $tokenId);
        Context::add(ApiRequestContext::OAUTH_GRANT_TYPE, OAuthGrantType::ClientCredentials->value);
        Context::add(ApiRequestContext::TRACE_ID, $traceId);
        Context::add(ApiRequestContext::FAILURE_REASON, ApiRequestFailure::ValidationFailed->value);

        $this->getJson($this->endpoint, ['User-Agent' => 'TestBot/2.0'])
            ->assertOk();

        $this->assertDatabaseHas(ApiRequestLog::class, [
            'trace_id' => $traceId,
            'user_id' => $user->id,
            'principal_type' => 'client',
            'oauth_client_id' => $clientId,
            'token_id' => $tokenId,
            'grant_type' => 'client_credentials',
            'method' => 'GET',
            'path' => 'api/test',
            'status_code' => 200,
            'user_agent' => 'TestBot/2.0',
            'failure_reason' => ApiRequestFailure::ValidationFailed->value,
        ]);
    }
}
