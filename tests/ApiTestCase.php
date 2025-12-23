<?php

declare(strict_types=1);

namespace Tests;

use App\Domains\Auth\Models\AccessToken;
use App\Domains\User\Models\User;
use Illuminate\Testing\TestResponse;

abstract class ApiTestCase extends TestCase
{
    protected string $rawAccessToken = 'password';

    protected User $apiUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiUser = User::factory()
            ->api()
            ->has(AccessToken::factory()->state([
                'token_hash' => AccessToken::hashFromPlain($this->rawAccessToken),
                'expires_at' => null,
            ]), 'access_tokens')
            ->state([
                'username' => 'api-adoes-test',
                'first_name' => 'ADOES',
                'last_name' => 'API',
            ])
            ->createOne();
    }

    /**
     * The endpoint that the test case should target.
     */
    abstract public function endpoint(): string;

    /**
     * List of HTTP method(s) that {@see self::endpoint()} supports.
     *
     * Some GETs have a POST for large filter bodies, so both should be specified in that case.
     *
     * @return list<'get'|'post'|'put'|'patch'|'delete'>
     */
    abstract public static function methods(): array;

    protected function bearerAuthenticationHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->rawAccessToken];
    }

    /**
     * Make an authenticated JSON request to the endpoint.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function authenticatedJson(string $method, array $data = [], array $headers = []): TestResponse
    {
        return $this->json(
            $method,
            $this->endpoint(),
            $data,
            array_merge($this->bearerAuthenticationHeader(), $headers),
        );
    }

    /**
     * Make an authenticated GET request to the endpoint.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    protected function authenticatedGet(array $query = [], array $headers = []): TestResponse
    {
        $url = $this->endpoint();

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $this->getJson($url, array_merge($this->bearerAuthenticationHeader(), $headers));
    }

    /**
     * Make an authenticated POST request to the endpoint.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function authenticatedPost(array $data = [], array $headers = []): TestResponse
    {
        return $this->postJson(
            $this->endpoint(),
            $data,
            array_merge($this->bearerAuthenticationHeader(), $headers),
        );
    }

    /**
     * Make an authenticated PUT request to the endpoint.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function authenticatedPut(array $data = [], array $headers = []): TestResponse
    {
        return $this->putJson(
            $this->endpoint(),
            $data,
            array_merge($this->bearerAuthenticationHeader(), $headers),
        );
    }

    /**
     * Make an authenticated PATCH request to the endpoint.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function authenticatedPatch(array $data = [], array $headers = []): TestResponse
    {
        return $this->patchJson(
            $this->endpoint(),
            $data,
            array_merge($this->bearerAuthenticationHeader(), $headers),
        );
    }

    /**
     * Make an authenticated DELETE request to the endpoint.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function authenticatedDelete(array $data = [], array $headers = []): TestResponse
    {
        return $this->deleteJson(
            $this->endpoint(),
            $data,
            array_merge($this->bearerAuthenticationHeader(), $headers),
        );
    }

    /**
     * Assert that all endpoint methods require authentication.
     */
    public function test_requires_authentication(): void
    {
        foreach (static::methods() as $method) {
            $this->json($method, $this->endpoint())
                ->assertUnauthorized();
        }
    }
}
