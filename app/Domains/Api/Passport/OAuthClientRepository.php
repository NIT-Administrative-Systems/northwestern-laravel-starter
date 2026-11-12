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
 * Passport's client repository. A client ID is a UUID, found as Chassis finds it, or the URL
 * of an MCP client's metadata document, which finds or creates that client through
 * {@see ClientMetadataDocuments}. Passport identifies the client by its UUID after that.
 */
class OAuthClientRepository extends ChassisOAuthClientRepository
{
    public function find(string|int $id): ?Client
    {
        if (! str_starts_with((string) $id, 'https://')) {
            return parent::find($id);
        }

        // Resolved here: registering a client needs this repository, so it can't be injected.
        $client = resolve(ClientMetadataDocuments::class)->find((string) $id);

        return $client instanceof OAuthClient ? $this->allowingLoopbackPort($client) : null;
    }

    /**
     * A native client such as Claude Code lists `http://localhost/callback` in its document and
     * returns on whichever port it opened (RFC 8252). Passport ignores the port only for
     * `127.0.0.1`, so a `localhost` redirect this request asks for joins the client's list for
     * this request when it matches one of them without the port. Nothing saves the copy.
     */
    private function allowingLoopbackPort(OAuthClient $client): OAuthClient
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

        // Passport compares the request's redirect URI exactly, so it joins the list as sent.
        $copy = clone $client;
        $copy->redirect_uris = [...$client->redirect_uris, $requested];

        return $copy;
    }
}
