<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Domains\Api\Enums\ClientOrigin;
use App\Domains\Api\Models\OAuthClient;
use App\Mcp\ClientMetadataDocuments;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RunsAuthorizationCodeFlow;
use Tests\TestCase;

#[CoversClass(ClientMetadataDocuments::class)]
final class ClientMetadataDocumentsTest extends TestCase
{
    use RunsAuthorizationCodeFlow;

    private ClientMetadataDocuments $documents;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mcp.enabled' => true, 'mcp.client_metadata_hosts' => ['claude.ai']]);
        $this->documents = resolve(ClientMetadataDocuments::class);
    }

    public function test_it_accepts_an_https_url_with_a_path_on_a_listed_host(): void
    {
        $this->assertTrue($this->documents->accepts(self::CLAUDE_CODE_METADATA_URL));
        $this->assertTrue($this->documents->accepts('https://claude.ai/oauth/metadata.json?v=2'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedUrls(): iterable
    {
        yield 'plain http' => ['http://claude.ai/oauth/claude-code-client-metadata'];
        yield 'an unlisted host' => ['https://claude.example/oauth/claude-code-client-metadata'];
        yield 'a subdomain of a listed host' => ['https://evil.claude.ai/oauth/claude-code-client-metadata'];
        yield 'a port' => ['https://claude.ai:8443/oauth/claude-code-client-metadata'];
        yield 'credentials' => ['https://user:pass@claude.ai/oauth/claude-code-client-metadata'];
        yield 'a fragment' => ['https://claude.ai/oauth/claude-code-client-metadata#top'];
        yield 'no path' => ['https://claude.ai'];
        yield 'the root path' => ['https://claude.ai/'];
        yield 'a dot segment' => ['https://claude.ai/oauth/../claude-code-client-metadata'];
        yield 'not a URL' => ['https:///nothing'];
    }

    #[DataProvider('refusedUrls')]
    public function test_it_refuses_other_urls_without_fetching_them(string $url): void
    {
        Http::preventStrayRequests();

        $this->assertFalse($this->documents->accepts($url));
        $this->assertNull($this->documents->find($url));
    }

    public function test_it_accepts_nothing_while_mcp_is_off_or_no_host_is_listed(): void
    {
        config(['mcp.client_metadata_hosts' => []]);
        $this->assertFalse($this->documents->accepts(self::CLAUDE_CODE_METADATA_URL));

        config(['mcp.client_metadata_hosts' => ['claude.ai'], 'mcp.enabled' => false]);
        $this->assertFalse($this->documents->accepts(self::CLAUDE_CODE_METADATA_URL));
    }

    public function test_it_creates_a_public_mcp_client_from_the_document(): void
    {
        $this->fakeClientMetadataDocument();

        $client = $this->documents->find(self::CLAUDE_CODE_METADATA_URL);

        $this->assertInstanceOf(OAuthClient::class, $client);
        $this->assertSame('Claude Code', $client->name);
        $this->assertSame(['http://localhost/callback', 'http://127.0.0.1/callback'], $client->redirect_uris);
        $this->assertSame(self::CLAUDE_CODE_METADATA_URL, $client->client_metadata_url);
        $this->assertSame(ClientOrigin::Dynamic, $client->origin);
        $this->assertSame(['mcp:use'], $client->scopes);
        $this->assertFalse($client->confidential());
        Http::assertSent(fn ($request): bool => $request->url() === self::CLAUDE_CODE_METADATA_URL && $request->hasHeader('Accept', 'application/json'));
    }

    // Everyone who connects Claude Code shares one
    // client, and the document is fetched daily.
    public function test_it_reuses_the_client_and_the_cached_document(): void
    {
        $this->fakeClientMetadataDocument();

        $first = $this->documents->find(self::CLAUDE_CODE_METADATA_URL);
        $second = $this->documents->find(self::CLAUDE_CODE_METADATA_URL);

        $this->assertInstanceOf(OAuthClient::class, $first);
        $this->assertSame($first->getKey(), $second?->getKey());
        Http::assertSentCount(1);
        $this->assertSame(1, OAuthClient::query()->count());
    }

    public function test_it_keeps_the_client_in_step_with_the_document_once_the_cache_expires(): void
    {
        $document = ['client_id' => self::CLAUDE_CODE_METADATA_URL, 'client_name' => 'Claude Code', 'redirect_uris' => ['http://localhost/callback']];
        Http::fake([self::CLAUDE_CODE_METADATA_URL => Http::sequence()
            ->push($document)
            ->push([...$document, 'client_name' => 'Claude Code (Beta)', 'redirect_uris' => ['http://127.0.0.1/callback']])]);
        $client = $this->documents->find(self::CLAUDE_CODE_METADATA_URL);
        $this->assertInstanceOf(OAuthClient::class, $client);

        $this->travel(25)->hours();

        $updated = $this->documents->find(self::CLAUDE_CODE_METADATA_URL);

        $this->assertSame($client->getKey(), $updated?->getKey());
        $this->assertSame('Claude Code (Beta)', $client->refresh()->name);
        $this->assertSame(['http://127.0.0.1/callback'], $client->redirect_uris);
    }

    // As a client that registers itself would register again.
    public function test_a_revoked_client_is_replaced_on_the_next_connection(): void
    {
        $this->fakeClientMetadataDocument();
        $revoked = $this->documents->find(self::CLAUDE_CODE_METADATA_URL);
        $this->assertInstanceOf(OAuthClient::class, $revoked);
        $revoked->forceFill(['revoked' => true])->save();

        $replacement = $this->documents->find(self::CLAUDE_CODE_METADATA_URL);

        $this->assertInstanceOf(OAuthClient::class, $replacement);
        $this->assertNotSame($revoked->getKey(), $replacement->getKey());
    }

    public function test_a_document_without_a_name_is_named_after_its_host(): void
    {
        $this->fakeClientMetadataDocument(['client_name' => '  ']);

        $this->assertSame('claude.ai', $this->documents->find(self::CLAUDE_CODE_METADATA_URL)?->name);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidDocuments(): iterable
    {
        yield 'another client ID' => [['client_id' => 'https://claude.ai/oauth/other'], 'its client_id is not its own URL'];
        yield 'no redirect URIs' => [['redirect_uris' => []], 'redirect uris field is required'];
        yield 'a plain HTTP redirect to another site' => [['redirect_uris' => ['http://claude.example/callback']], 'Each redirect URI must use HTTPS'];
        yield 'a client secret' => [['client_secret' => 'shh'], 'client secret field is prohibited'];
        yield 'a confidential client' => [['token_endpoint_auth_method' => 'client_secret_basic'], 'token endpoint auth method is invalid'];
        yield 'a name that is not text' => [['client_name' => ['Claude']], 'client name field must be a string'];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidDocuments')]
    public function test_it_refuses_an_invalid_document(array $overrides, string $reason): void
    {
        $this->fakeClientMetadataDocument($overrides);
        Log::shouldReceive('warning')->once()->withArgs(fn (string $message, array $context): bool => $message === 'MCP client metadata document refused'
            && $context['url'] === self::CLAUDE_CODE_METADATA_URL
            && str_contains((string) $context['reason'], $reason));

        $this->assertNull($this->documents->find(self::CLAUDE_CODE_METADATA_URL));

        $this->assertSame(0, OAuthClient::query()->count());
    }

    /**
     * @return iterable<string, array{\Closure(): mixed, string}>
     */
    public static function unusableResponses(): iterable
    {
        yield 'a server error' => [fn () => Http::response('', 500), 'HTTP 500'];
        yield 'a redirect, which is not followed' => [fn () => Http::response('', 302, ['Location' => 'https://claude.example/metadata']), 'HTTP 302'];
        yield 'a document over 5 KB' => [fn () => Http::response(['client_id' => self::CLAUDE_CODE_METADATA_URL, 'padding' => str_repeat('x', 5200)]), 'larger than 5120 bytes'];
        yield 'not JSON' => [fn () => Http::response('<html></html>'), 'its client_id is not its own URL'];
        yield 'no connection' => [fn () => throw new ConnectionException('Connection refused'), 'Connection refused'];
    }

    /**
     * @param  \Closure(): mixed  $response
     */
    #[DataProvider('unusableResponses')]
    public function test_it_refuses_a_document_it_cannot_use(\Closure $response, string $reason): void
    {
        Http::fake([self::CLAUDE_CODE_METADATA_URL => $response]);
        Log::shouldReceive('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains((string) $context['reason'], $reason));

        $this->assertNull($this->documents->find(self::CLAUDE_CODE_METADATA_URL));
    }

    // A failure isn't cached, so the next connection tries again.
    public function test_a_refused_document_is_fetched_again_next_time(): void
    {
        Http::fake([self::CLAUDE_CODE_METADATA_URL => Http::sequence()->push('', 503)->push([
            'client_id' => self::CLAUDE_CODE_METADATA_URL,
            'client_name' => 'Claude Code',
            'redirect_uris' => ['http://localhost/callback'],
        ])]);

        $refused = $this->documents->find(self::CLAUDE_CODE_METADATA_URL);
        $found = $this->documents->find(self::CLAUDE_CODE_METADATA_URL);

        $this->assertNull($refused);
        $this->assertSame('Claude Code', $found?->name);
        $this->assertTrue(Cache::has('mcp:client-metadata:' . hash('sha256', self::CLAUDE_CODE_METADATA_URL)));
    }
}
