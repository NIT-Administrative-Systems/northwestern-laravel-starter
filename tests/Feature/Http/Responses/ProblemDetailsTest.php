<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Responses;

use App\Domains\Core\ValueObjects\ApiRequestContext;
use App\Http\Responses\ProblemDetails;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ProblemDetails::class)]
class ProblemDetailsTest extends TestCase
{
    public function test_response_returns_expected_structure(): void
    {
        $response = ProblemDetails::response(
            status: 400,
            title: 'Bad Request',
            detail: 'Invalid input',
            type: 'https://example.com/errors/bad-request',
            instance: '/example',
            extensions: ['foo' => 'bar'],
            headers: ['X-Custom' => 'yes']
        );

        $json = $response->getData(true);

        $this->assertSame(400, $response->status());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertSame('yes', $response->headers->get('X-Custom'));

        $this->assertSame('Bad Request', $json['title']);
        $this->assertSame('Invalid input', $json['detail']);
        $this->assertSame('/example', $json['instance']);
        $this->assertSame('https://example.com/errors/bad-request', $json['type']);
        $this->assertSame('bar', $json['foo']);
    }

    public function test_trace_id_is_attached_when_present(): void
    {
        Context::add(ApiRequestContext::TRACE_ID, 'abc123');

        $response = ProblemDetails::response(500, 'Error');
        $json = $response->getData(true);

        $this->assertSame('abc123', $json['trace_id']);
    }

    public function test_trace_id_is_omitted_when_missing(): void
    {
        Context::forget(ApiRequestContext::TRACE_ID);

        $response = ProblemDetails::response(500, 'Error');
        $json = $response->getData(true);

        $this->assertArrayNotHasKey('trace_id', $json);
    }

    public function test_unauthorized_response(): void
    {
        config(['auth.api.auth_realm' => 'TestRealm']);

        $response = ProblemDetails::unauthorized();
        $json = $response->getData(true);

        $this->assertSame(401, $response->status());
        $this->assertSame('Unauthorized', $json['title']);
        $this->assertSame('Bearer realm="TestRealm"', $response->headers->get('WWW-Authenticate'));
    }

    public function test_forbidden_response(): void
    {
        $response = ProblemDetails::forbidden();
        $json = $response->getData(true);

        $this->assertSame(403, $response->status());
        $this->assertSame('Forbidden', $json['title']);
    }

    public function test_not_found_response(): void
    {
        $response = ProblemDetails::notFound();
        $json = $response->getData(true);

        $this->assertSame(404, $response->status());
        $this->assertSame('Not Found', $json['title']);
    }

    public function test_unprocessable_entity_with_errors(): void
    {
        $errors = ['email' => ['Invalid email']];
        $response = ProblemDetails::unprocessableEntity(errors: $errors);
        $json = $response->getData(true);

        $this->assertSame(422, $response->status());
        $this->assertSame($errors, $json['errors']);
    }

    public function test_service_unavailable_response(): void
    {
        $response = ProblemDetails::serviceUnavailable(
            detail: 'Maintenance window',
            retryAfter: 7200,
            headers: ['X-Meta' => 'planned']
        );

        $json = $response->getData(true);

        $this->assertSame(503, $response->status());
        $this->assertSame('Service Unavailable', $json['title']);
        $this->assertSame('Maintenance window', $json['detail']);

        // Check headers
        $this->assertSame('7200', $response->headers->get('Retry-After'));
        $this->assertSame('planned', $response->headers->get('X-Meta'));

        // Standard content-type
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    public function test_too_many_requests_response_includes_retry_after(): void
    {
        $response = ProblemDetails::tooManyRequests(retryAfter: 120);
        $json = $response->getData(true);

        $this->assertSame(429, $response->status());
        $this->assertSame('120', $response->headers->get('Retry-After'));
        $this->assertSame('Too Many Requests', $json['title']);
    }

    public function test_method_not_allowed_includes_allow_header(): void
    {
        $response = ProblemDetails::methodNotAllowed(['GET', 'POST']);
        $json = $response->getData(true);

        $this->assertSame(405, $response->status());
        $this->assertSame('GET, POST', $response->headers->get('Allow'));
        $this->assertSame('Method Not Allowed', $json['title']);
    }

    public function test_method_not_allowed_accepts_comma_separated_string(): void
    {
        $response = ProblemDetails::methodNotAllowed('PUT, PATCH');

        $json = $response->getData(true);

        $this->assertSame(405, $response->status());
        $this->assertSame('Method Not Allowed', $json['title']);
        $this->assertSame('PUT, PATCH', $response->headers->get('Allow'));
    }

    public function test_conflict_response_with_extensions(): void
    {
        $response = ProblemDetails::conflict(
            detail: 'Conflict occurred',
            extensions: ['reason' => 'duplicate']
        );

        $json = $response->getData(true);

        $this->assertSame(409, $response->status());
        $this->assertSame('duplicate', $json['reason']);
    }

    public function test_payload_too_large_response(): void
    {
        $response = ProblemDetails::payloadTooLarge();
        $json = $response->getData(true);

        $this->assertSame(413, $response->status());
        $this->assertSame('Payload Too Large', $json['title']);
    }

    public function test_internal_server_error_response(): void
    {
        $response = ProblemDetails::internalServerError();
        $json = $response->getData(true);

        $this->assertSame(500, $response->status());
        $this->assertSame('Internal Server Error', $json['title']);
    }

    public function test_bad_request_with_extensions(): void
    {
        $response = ProblemDetails::badRequest(
            detail: 'Bad input',
            extensions: ['field' => 'email']
        );

        $json = $response->getData(true);

        $this->assertSame(400, $response->status());
        $this->assertSame('email', $json['field']);
    }
}
