<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\Passport;

use App\Domains\Api\Models\OAuthClient;
use App\Domains\Api\Passport\OAuthClientRepository;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversClass(OAuthClientRepository::class)]
final class OAuthClientRepositoryTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    private ClientRepository $clients;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mcp.enabled' => true, 'mcp.client_metadata_urls' => [self::CLAUDE_CODE_METADATA_URL]]);
        $this->fakeClientMetadataDocument();
        $this->clients = resolve(ClientRepository::class);
    }

    public function test_it_is_passports_client_repository(): void
    {
        $this->assertInstanceOf(OAuthClientRepository::class, $this->clients);
    }

    public function test_it_finds_a_client_by_its_uuid_and_treats_other_ids_as_unknown(): void
    {
        $application = $this->registerApplication();

        $this->assertSame($application->getKey(), $this->clients->find($application->getKey())?->getKey());
        $this->assertNull($this->clients->find('not-a-uuid'));
        $this->assertNull($this->clients->find('https://claude.example/oauth/metadata'));
    }

    public function test_it_finds_an_mcp_client_by_its_metadata_document_url(): void
    {
        $client = $this->clients->findActive(self::CLAUDE_CODE_METADATA_URL);

        $this->assertInstanceOf(OAuthClient::class, $client);
        $this->assertSame(self::CLAUDE_CODE_METADATA_URL, $client->client_metadata_url);
    }

    // Claude Code lists http://localhost/callback and returns on the port it opened.
    public function test_a_localhost_redirect_on_any_port_is_allowed_for_that_request_only(): void
    {
        $this->withRedirectUri('http://localhost:53143/callback');

        $client = $this->clients->find(self::CLAUDE_CODE_METADATA_URL);

        $this->assertInstanceOf(OAuthClient::class, $client);
        $this->assertSame(['http://localhost/callback', 'http://127.0.0.1/callback', 'http://localhost:53143/callback'], $client->redirect_uris);
        $this->assertSame(['http://localhost/callback', 'http://127.0.0.1/callback'], OAuthClient::query()->sole()->redirect_uris);
    }

    public function test_other_redirects_are_left_to_passport(): void
    {
        foreach (['http://localhost:53143/other', 'http://localhost/callback', 'http://127.0.0.1:53143/callback', 'https://localhost:53143/callback', 'http:///callback', 'not a url', ''] as $uri) {
            $this->withRedirectUri($uri);

            $client = $this->clients->find(self::CLAUDE_CODE_METADATA_URL);

            $this->assertInstanceOf(OAuthClient::class, $client);
            $this->assertSame(['http://localhost/callback', 'http://127.0.0.1/callback'], $client->redirect_uris, $uri);
        }
    }

    private function withRedirectUri(string $uri): void
    {
        $this->app->instance('request', Request::create('/oauth/authorize', 'GET', ['redirect_uri' => $uri]));
    }
}
