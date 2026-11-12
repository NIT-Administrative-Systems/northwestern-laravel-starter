<?php

declare(strict_types=1);

namespace App\Domains\Api\Passport;

use App\Domains\Api\Models\OAuthClient;
use App\Mcp\ClientMetadataDocuments;
use Illuminate\Support\Uri;
use Laravel\Passport\Client;
use League\Uri\Exceptions\SyntaxError;
use Northwestern\SysDev\Chassis\Passport\OAuthClientRepository as ChassisOAuthClientRepository;

/**
 * Passport's client repository. A UUID client ID is found as Chassis
 * finds it, and the URL of an MCP client's metadata document goes
 * to {@see ClientMetadataDocuments}, which returns the client.
 */
class OAuthClientRepository extends ChassisOAuthClientRepository
{
    public function find(string|int $id): ?Client
    {
        if (! str_starts_with((string) $id, 'https://')) {
            return parent::find($id);
        }

        // Resolved here because registering a client needs this repository.
        $client = resolve(ClientMetadataDocuments::class)->find((string) $id);

        return $client instanceof OAuthClient ? $this->withRequestedLoopbackRedirect($client) : null;
    }

    /**
     * Add the localhost redirect this request asks for when
     * one the client lists matches it without its port.
     *
     * Claude Code lists `http://localhost/callback` and returns on whichever port
     * it opened (RFC 8252), but Passport ignores the port only for `127.0.0.1`.
     *
     * The copy only serves this request; nothing saves it.
     */
    private function withRequestedLoopbackRedirect(OAuthClient $client): OAuthClient
    {
        $requested = request()->string('redirect_uri')->toString();

        try {
            $uri = Uri::of($requested);
        } catch (SyntaxError) {
            return $client;
        }

        if ($uri->scheme() !== 'http' || $uri->host() !== 'localhost' || $uri->port() === null || ! in_array($uri->withPort(null)->toString(), $client->redirect_uris, true)) {
            return $client;
        }

        // Passport matches the redirect URI exactly, so it joins the list as sent.
        $copy = clone $client;
        $copy->redirect_uris = [...$client->redirect_uris, $requested];

        return $copy;
    }
}
