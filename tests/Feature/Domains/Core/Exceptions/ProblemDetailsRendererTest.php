<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Exceptions;

use App\Domains\Core\Exceptions\ProblemDetailsRenderer;
use App\Domains\Core\ValueObjects\ApiRequestContext;
use ErrorException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\TestCase;
use Throwable;

#[CoversClass(ProblemDetailsRenderer::class)]
class ProblemDetailsRendererTest extends TestCase
{
    private ProblemDetailsRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('auth.api.auth_realm', 'Test Realm');
        $this->renderer = resolve(ProblemDetailsRenderer::class);
    }

    private function renderForRequest(Throwable $e, string $uri, array $server = []): ?JsonResponse
    {
        $request = Request::create($uri, 'GET', [], [], [], $server);

        $this->app->instance('request', $request);

        return $this->renderer->render($e, $request);
    }

    private function renderForApi(Throwable $e, string $uri = '/api/test'): JsonResponse
    {
        /** @var JsonResponse|null $response */
        $response = $this->renderForRequest($e, $uri, [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertNotNull($response);

        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));

        return $response;
    }

    #[DataProvider('simpleExceptionMappingProvider')]
    public function test_exceptions_are_mapped_to_expected_status_and_title(
        Throwable $exception,
        int $expectedStatus,
        string $expectedTitle
    ): void {
        $response = $this->renderForApi($exception);

        $this->assertSame($expectedStatus, $response->getStatusCode());

        $data = $response->getData(true);

        $this->assertSame($expectedStatus, $data['status']);
        $this->assertSame($expectedTitle, $data['title']);
        $this->assertSame('/api/test', $data['instance']);
        $this->assertSame('about:blank', $data['type']);
    }

    public static function simpleExceptionMappingProvider(): array
    {
        return [
            'authentication' => [
                new AuthenticationException(),
                401,
                'Unauthorized',
            ],
            'authorization (AuthorizationException)' => [
                new AuthorizationException(),
                403,
                'Forbidden',
            ],
            'authorization (AccessDeniedHttpException)' => [
                new AccessDeniedHttpException(),
                403,
                'Forbidden',
            ],
            'model not found' => [
                new ModelNotFoundException(),
                404,
                'Not Found',
            ],
            'not found http' => [
                new NotFoundHttpException(),
                404,
                'Not Found',
            ],
            'conflict' => [
                new ConflictHttpException(),
                409,
                'Conflict',
            ],
            'bad request (BadRequestException)' => [
                new BadRequestException(),
                400,
                'Bad Request',
            ],
            'bad request (ErrorException)' => [
                new ErrorException('boom'),
                400,
                'Bad Request',
            ],
            'bad request (NotAcceptableHttpException)' => [
                new NotAcceptableHttpException(),
                400,
                'Bad Request',
            ],
            'payload too large' => [
                new PostTooLargeException(),
                413,
                'Payload Too Large',
            ],
            'service unavailable' => [
                new ServiceUnavailableHttpException(),
                503,
                'Service Unavailable',
            ],
            'pdo => 500' => [
                new PDOException('db error'),
                500,
                'Internal Server Error',
            ],
            'default => 500' => [
                new \RuntimeException('unexpected'),
                500,
                'Internal Server Error',
            ],
        ];
    }

    public function test_does_not_overwrite_existing_failure_reason(): void
    {
        Context::add(ApiRequestContext::FAILURE_REASON, 'ip-denied');

        $exception = ValidationException::withMessages(['foo' => ['bar']]);

        $this->renderForApi($exception);

        $this->assertSame('ip-denied', Context::get(ApiRequestContext::FAILURE_REASON));
    }

    public function test_validation_exception_includes_errors_and_uses_422(): void
    {
        $exception = ValidationException::withMessages([
            'email' => ['The email field is required.'],
        ]);

        $response = $this->renderForApi($exception);
        $data = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(422, $data['status']);
        $this->assertSame('Unprocessable Entity', $data['title']);
        $this->assertArrayHasKey('errors', $data);
        $this->assertSame(['The email field is required.'], $data['errors']['email']);
    }

    public function test_method_not_allowed_sets_allow_header(): void
    {
        $exception = new MethodNotAllowedHttpException(['GET', 'POST']);

        $response = $this->renderForApi($exception);
        $data = $response->getData(true);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('Method Not Allowed', $data['title']);
        $this->assertSame('GET, POST', $response->headers->get('Allow'));
    }

    public function test_throttle_requests_uses_retry_after_header(): void
    {
        $exception = new ThrottleRequestsException(
            'Too many requests',
            null,
            ['Retry-After' => 120]
        );

        $response = $this->renderForApi($exception);
        $data = $response->getData(true);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame(429, $data['status']);
        $this->assertSame('Too Many Requests', $data['title']);
        $this->assertSame('Too many requests. Please try again later.', $data['detail']);
        $this->assertSame('120', $response->headers->get('Retry-After'));
    }

    public function test_http_exception_interface_fallback_uses_status_and_headers(): void
    {
        /** @var HttpException $exception */
        $exception = new HttpException(
            418,
            'I am a teapot',
            null,
            ['X-Foo' => 'bar']
        );

        $response = $this->renderForApi($exception);
        $data = $response->getData(true);

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame(418, $data['status']);
        $this->assertSame('HTTP Error', $data['title']);
        $this->assertSame('I am a teapot', $data['detail']);
        $this->assertSame('bar', $response->headers->get('X-Foo'));
    }

    public function test_unauthorized_includes_www_authenticate_header(): void
    {
        $exception = new AuthenticationException();

        $response = $this->renderForApi($exception);

        $this->assertSame(
            'Bearer realm="Test Realm"',
            $response->headers->get('WWW-Authenticate')
        );
    }

    public function test_non_api_html_request_returns_null(): void
    {
        $exception = new NotFoundHttpException();

        $response = $this->renderForRequest($exception, '/web/page', [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $this->assertNull($response);
    }

    public function test_non_api_but_json_request_is_still_rendered(): void
    {
        $exception = new NotFoundHttpException();

        $response = $this->renderForRequest($exception, '/non-api/path', [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = $response->getData(true);

        $this->assertSame(404, $data['status']);
        $this->assertSame('Not Found', $data['title']);
        $this->assertSame('/non-api/path', $data['instance']);
    }
}
