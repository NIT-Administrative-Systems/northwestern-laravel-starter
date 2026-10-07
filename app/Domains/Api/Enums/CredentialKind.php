<?php

declare(strict_types=1);

namespace App\Domains\Api\Enums;

use App\Domains\Api\Models\OAuthClient;
use App\Domains\Api\Models\OAuthToken;
use InvalidArgumentException;

/**
 * The kinds of credential that call the application: a person's personal access tokens, the
 * applications and MCP clients a person connects, and the service clients an API user owns.
 * One Passport model backs several kinds, so {@see self::of()} tells them apart.
 */
enum CredentialKind
{
    case PersonalAccessToken;
    case ConnectedApplication;
    case McpClient;
    case ServiceClient;

    /**
     * The kind of a client, or of a token by its client.
     *
     * @throws InvalidArgumentException When a token's client no longer exists
     */
    public static function of(OAuthClient|OAuthToken $credential): self
    {
        $client = $credential instanceof OAuthToken ? $credential->client : $credential;

        if (! $client instanceof OAuthClient) {
            throw new InvalidArgumentException('The token belongs to a client that no longer exists.');
        }

        return match (true) {
            $client->hasGrantType('personal_access') => self::PersonalAccessToken,
            $client->isMcpClient() => self::McpClient,
            $client->hasGrantType('client_credentials') => self::ServiceClient,
            default => self::ConnectedApplication,
        };
    }

    /**
     * Whether the feature this kind belongs to is turned on: `mcp.enabled` for MCP clients,
     * `api.enabled` for the rest.
     */
    public function isEnabled(): bool
    {
        return (bool) config($this === self::McpClient ? 'mcp.enabled' : 'api.enabled');
    }

    /**
     * The kind in a sentence, plural: "personal access tokens".
     */
    public function plural(): string
    {
        return match ($this) {
            self::PersonalAccessToken => 'personal access tokens',
            self::ConnectedApplication => 'connected applications',
            self::McpClient => 'MCP clients',
            self::ServiceClient => 'service clients',
        };
    }
}
