<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Domains\Api\Actions\Applications\RegisterOAuthApplication;
use App\Domains\Api\Enums\ClientOrigin;
use App\Domains\Api\Models\OAuthClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Laravel\Mcp\Server\Registrar;
use League\Uri\Exceptions\SyntaxError;
use Northwestern\SysDev\Chassis\Rules\OAuthRedirectUri;

/**
 * Client ID metadata documents, the MCP authorization spec's way for a client
 * to identify itself without registering: the client's `client_id` is the
 * HTTPS URL of a JSON document that gives its name and redirect URIs.
 *
 * Claude publishes one for Claude Code and one for its apps, on `claude.ai`.
 *
 * The server fetches documents only from hosts in `mcp.client_metadata_hosts`, so
 * an anonymous request can't make it fetch an address it picks, and a name from
 * a listed host's document is the host's word rather than the client's own.
 *
 * Each document becomes one MCP client shared by everyone who connects it.
 * When that client is revoked or pruned, the next connection creates a
 * new one, as a client that registers itself would register again.
 */
readonly class ClientMetadataDocuments
{
    /** The MCP authorization spec suggests refusing larger documents. */
    private const int MAX_BYTES = 5120;

    public function __construct(
        private RegisterOAuthApplication $register,
    ) {
    }

    /**
     * Determine whether `$clientId` is a document URL the server fetches: HTTPS on a
     * listed host, with a path and no port, credentials, fragment or dot segments.
     */
    public function accepts(string $clientId): bool
    {
        if (! config('mcp.enabled')) {
            return false;
        }

        try {
            $uri = Uri::of($clientId);
        } catch (SyntaxError) {
            return false;
        }

        return $uri->scheme() === 'https'
            && in_array($uri->host(), config('mcp.client_metadata_hosts'), true)
            && $uri->port() === null
            && $uri->user() === null
            && $uri->fragment() === null
            && $uri->pathSegments()->isNotEmpty()
            && $uri->pathSegments()->intersect(['.', '..'])->isEmpty();
    }

    /**
     * Get the MCP client for the document at `$url`, created on first
     * use and kept in step with the document, or null when the URL
     * isn't accepted or the document is unreachable or invalid.
     */
    public function find(string $url): ?OAuthClient
    {
        if (! $this->accepts($url)) {
            return null;
        }

        /** @var array{name: non-empty-string, redirect_uris: list<non-empty-string>}|null $metadata */
        $metadata = Cache::remember('mcp:client-metadata:' . hash('sha256', $url), now()->addDay(), fn (): ?array => $this->fetch($url));

        if ($metadata === null) {
            return null;
        }

        $client = OAuthClient::query()->mcpClients()->where('client_metadata_url', $url)->where('revoked', false)->latest()->first();

        if ($client === null) {
            [, $client] = ($this->register)(
                name: $metadata['name'],
                redirectUris: $metadata['redirect_uris'],
                confidential: false,
                scopes: [Registrar::OAUTH_SCOPE],
                origin: ClientOrigin::Dynamic,
                clientMetadataUrl: $url,
            );

            return $client;
        }

        $client->forceFill(['name' => $metadata['name'], 'redirect_uris' => $metadata['redirect_uris']]);

        if ($client->isDirty()) {
            $client->save();
        }

        return $client;
    }

    /**
     * @return array{name: non-empty-string, redirect_uris: list<non-empty-string>}|null
     */
    private function fetch(string $url): ?array
    {
        try {
            $response = Http::accept('application/json')->connectTimeout(3)->timeout(5)->withoutRedirecting()->get($url);
        } catch (ConnectionException $exception) {
            return $this->refuse($url, $exception->getMessage());
        }

        if (! $response->ok()) {
            return $this->refuse($url, "HTTP {$response->status()}");
        }

        if (strlen($response->body()) > self::MAX_BYTES) {
            return $this->refuse($url, 'larger than ' . self::MAX_BYTES . ' bytes');
        }

        $document = $response->json();

        if (! is_array($document) || ($document['client_id'] ?? null) !== $url) {
            return $this->refuse($url, 'its client_id is not its own URL');
        }

        // A public client only: Passport would need a secret this document can't hold.
        $validator = Validator::make($document, [
            'client_name' => ['nullable', 'string', 'max:255'],
            'redirect_uris' => ['required', 'array', 'list', 'min:1', 'max:10'],
            'redirect_uris.*' => ['bail', 'required', 'string', 'max:2048', new OAuthRedirectUri(config('mcp.custom_schemes', []))],
            'token_endpoint_auth_method' => ['nullable', 'in:none'],
            'client_secret' => ['prohibited'],
        ]);

        if ($validator->fails()) {
            return $this->refuse($url, $validator->errors()->first());
        }

        $name = Str::limit(trim((string) ($document['client_name'] ?? '')), 255, '');

        /** @var list<non-empty-string> $redirectUris */
        $redirectUris = $document['redirect_uris'];

        return [
            'name' => $name !== '' ? $name : (string) Uri::of($url)->host(),
            'redirect_uris' => $redirectUris,
        ];
    }

    private function refuse(string $url, string $reason): null
    {
        Log::warning('MCP client metadata document refused', ['url' => $url, 'reason' => $reason]);

        return null;
    }
}
