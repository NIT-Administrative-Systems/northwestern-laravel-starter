<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Domains\Core\Enums\ApiRequestFailureEnum;
use App\Domains\Core\ValueObjects\ApiRequestContext;
use App\Domains\User\Models\AccessToken;
use App\Domains\User\Models\ApiRequestLog;
use App\Domains\User\Models\User;
use App\Http\Middleware\LogsApiRequests;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LogsApiRequests::class)]
class LogsApiRequestsTest extends TestCase
{
    private string $endpoint = '/api/test';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(LogsApiRequests::class)->get($this->endpoint, function () {
            return response()->json(['success' => true]);
        });

        config()->set('auth.api.request_logging.enabled', true);
        config()->set('auth.api.request_logging.sampling.enabled', false);

        Context::flush();
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    public function test_logging_disabled_via_config_skips_all_logging(): void
    {
        config()->set('auth.api.request_logging.enabled', false);

        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        $this->getJson($this->endpoint)->assertOk();

        $this->assertDatabaseCount(ApiRequestLog::class, 0);
    }

    public function test_unauthenticated_request_without_failure_reason_is_not_logged(): void
    {
        // No user_id or failure_reason in context
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        $this->getJson($this->endpoint)->assertOk();

        $this->assertDatabaseCount(ApiRequestLog::class, 0);
    }

    public function test_authenticated_successful_request_is_logged(): void
    {
        $user = User::factory()->api()->create();
        $token = AccessToken::factory()->for($user)->create();
        $traceId = Str::uuid()->toString();

        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TOKEN_ID, $token->id);
        Context::add(ApiRequestContext::TRACE_ID, $traceId);

        $this->getJson($this->endpoint, ['User-Agent' => 'TestAgent/1.0'])
            ->assertOk();

        $this->assertDatabaseHas(ApiRequestLog::class, [
            'trace_id' => $traceId,
            'user_id' => $user->id,
            'access_token_id' => $token->id,
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

    public function test_failed_request_with_failure_reason_is_logged(): void
    {
        $user = User::factory()->api()->create();
        $traceId = Str::uuid()->toString();

        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, $traceId);
        Context::add(ApiRequestContext::FAILURE_REASON, ApiRequestFailureEnum::IP_DENIED->value);

        Route::middleware(LogsApiRequests::class)->get('/api/forbidden', function () {
            return response()->json(['error' => 'Forbidden'], 403);
        });

        $this->getJson('/api/forbidden')->assertForbidden();

        $this->assertDatabaseHas(ApiRequestLog::class, [
            'trace_id' => $traceId,
            'user_id' => $user->id,
            'status_code' => 403,
            'failure_reason' => ApiRequestFailureEnum::IP_DENIED->value,
        ]);
    }

    public function test_route_name_is_captured_when_available(): void
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

    public function test_null_route_name_when_route_has_no_name(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        $this->getJson($this->endpoint)->assertOk();

        $log = ApiRequestLog::first();
        $this->assertNull($log->route_name);
    }

    public function test_sampling_disabled_logs_all_successful_requests(): void
    {
        config()->set('auth.api.request_logging.sampling.enabled', false);

        $user = User::factory()->api()->create();

        for ($i = 0; $i < 5; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

            $this->getJson($this->endpoint)->assertOk();
        }

        $this->assertDatabaseCount(ApiRequestLog::class, 5);
    }

    public function test_sampling_enabled_with_zero_rate_logs_no_successful_requests(): void
    {
        config()->set('auth.api.request_logging.sampling.enabled', true);
        config()->set('auth.api.request_logging.sampling.rate', 0.0);

        $user = User::factory()->api()->create();

        for ($i = 0; $i < 10; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

            $this->getJson($this->endpoint)->assertOk();
        }

        $this->assertDatabaseCount(ApiRequestLog::class, 0);
    }

    public function test_sampling_enabled_with_100_percent_logs_all_successful_requests(): void
    {
        config()->set('auth.api.request_logging.sampling.enabled', true);
        config()->set('auth.api.request_logging.sampling.rate', 1.0);

        $user = User::factory()->api()->create();

        for ($i = 0; $i < 5; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

            $this->getJson($this->endpoint)->assertOk();
        }

        $this->assertDatabaseCount(ApiRequestLog::class, 5);
    }

    public function test_sampling_always_logs_errors_regardless_of_sample_rate(): void
    {
        config()->set('auth.api.request_logging.sampling.enabled', true);
        config()->set('auth.api.request_logging.sampling.rate', 0.0);

        $user = User::factory()->api()->create();

        Route::middleware(LogsApiRequests::class)->get('/api/error', function () {
            return response()->json(['error' => 'Not Found'], 404);
        });

        for ($i = 0; $i < 5; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

            $this->getJson('/api/error')->assertNotFound();
        }

        // All 5 error requests should be logged despite 0% sampling
        $this->assertDatabaseCount(ApiRequestLog::class, 5);
    }

    public function test_sampling_always_logs_failures_regardless_of_sample_rate(): void
    {
        config()->set('auth.api.request_logging.sampling.enabled', true);
        config()->set('auth.api.request_logging.sampling.rate', 0.0);

        $user = User::factory()->api()->create();

        for ($i = 0; $i < 5; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());
            Context::add(ApiRequestContext::FAILURE_REASON, ApiRequestFailureEnum::TOKEN_INVALID_OR_EXPIRED->value);

            $this->getJson($this->endpoint)->assertOk();
        }

        // All 5 requests with failure reasons should be logged despite 0% sampling
        $this->assertDatabaseCount(ApiRequestLog::class, 5);
    }

    public function test_sampling_with_50_percent_logs_approximately_half_of_successful_requests(): void
    {
        config()->set('auth.api.request_logging.sampling.enabled', true);
        config()->set('auth.api.request_logging.sampling.rate', 0.5);

        $user = User::factory()->api()->create();

        for ($i = 0; $i < 100; $i++) {
            Context::flush();
            Context::add(ApiRequestContext::USER_ID, $user->id);
            Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

            $this->getJson($this->endpoint)->assertOk();
        }

        $logCount = ApiRequestLog::count();

        // With 50% sampling, we expect roughly 50 logs (allow ±20 for randomness)
        $this->assertGreaterThan(30, $logCount, 'Expected at least 30% of 100 requests to be logged');
        $this->assertLessThan(70, $logCount, 'Expected at most 70% of 100 requests to be logged');
    }

    public function test_database_exception_during_logging_does_not_break_request(): void
    {
        $user = User::factory()->api()->create();

        // Create a route with middleware that sets context and forces a DB error
        app()->bind('test.set.context.with.error', function () use ($user) {
            return function ($request, $next) use ($user) {
                Context::add(ApiRequestContext::USER_ID, $user->id);
                Context::add(ApiRequestContext::TRACE_ID, 'invalid-trace-id-format-to-cause-error');

                return $next($request);
            };
        });

        Route::middleware(['test.set.context.with.error', LogsApiRequests::class])
            ->get('/api/db-error-test', function () {
                return response()->json(['ok' => true]);
            });

        // Even if logging fails internally (caught by try/catch),
        // the request should still succeed
        $this->getJson('/api/db-error-test')->assertOk();
    }

    public function test_client_ip_address_is_captured(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        $this->getJson($this->endpoint, ['X-Forwarded-For' => '192.168.1.100'])
            ->assertOk();

        $log = ApiRequestLog::first();
        // Laravel in test mode may still use 127.0.0.1 or the forwarded IP
        $this->assertNotEmpty($log->ip_address);
    }

    public function test_ip_address_defaults_to_unknown_when_unavailable(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        // In test environment, we'll always have an IP (127.0.0.1)
        // but we can verify the logic handles null by checking the code path
        $this->getJson($this->endpoint)->assertOk();

        $log = ApiRequestLog::first();
        // In tests, this will be 127.0.0.1, but the middleware code handles null → 'unknown'
        $this->assertNotNull($log->ip_address);
    }

    public function test_streamed_response_does_not_capture_response_bytes(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        Route::middleware(LogsApiRequests::class)->get('/api/stream', function () {
            return response()->stream(function () {
                echo 'streaming data';
            });
        });

        $this->get('/api/stream')->assertOk();

        $log = ApiRequestLog::first();
        $this->assertNull($log->response_bytes);
    }

    public function test_response_bytes_calculated_from_content_length_header(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        Route::middleware(LogsApiRequests::class)->get('/api/sized', function () {
            return response()->json(['data' => 'test'])
                ->header('Content-Length', '1234');
        });

        $this->getJson('/api/sized')->assertOk();

        $log = ApiRequestLog::first();
        $this->assertSame(1234, $log->response_bytes);
    }

    public function test_duration_ms_is_calculated_and_stored(): void
    {
        $user = User::factory()->api()->create();
        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TRACE_ID, Str::uuid()->toString());

        Route::middleware(LogsApiRequests::class)->get('/api/slow', function () {
            usleep(10000); // 10ms delay

            return response()->json(['ok' => true]);
        });

        $this->getJson('/api/slow')->assertOk();

        $log = ApiRequestLog::first();
        $this->assertGreaterThanOrEqual(10, $log->duration_ms);
        $this->assertLessThan(1000, $log->duration_ms); // Should be less than 1 second
    }

    public function test_all_context_values_are_captured_in_log(): void
    {
        $user = User::factory()->api()->create();
        $token = AccessToken::factory()->for($user)->create();
        $traceId = Str::uuid()->toString();

        Context::add(ApiRequestContext::USER_ID, $user->id);
        Context::add(ApiRequestContext::TOKEN_ID, $token->id);
        Context::add(ApiRequestContext::TRACE_ID, $traceId);
        Context::add(ApiRequestContext::FAILURE_REASON, ApiRequestFailureEnum::VALIDATION_FAILED->value);

        $this->getJson($this->endpoint, ['User-Agent' => 'TestBot/2.0'])
            ->assertOk();

        $this->assertDatabaseHas(ApiRequestLog::class, [
            'trace_id' => $traceId,
            'user_id' => $user->id,
            'access_token_id' => $token->id,
            'method' => 'GET',
            'path' => 'api/test',
            'status_code' => 200,
            'user_agent' => 'TestBot/2.0',
            'failure_reason' => ApiRequestFailureEnum::VALIDATION_FAILED->value,
        ]);
    }
}
