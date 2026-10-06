<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Models\OAuthClient;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;

/**
 * Registers an external application that people can connect to their account through the
 * authorization code flow. A confidential application, such as a web server, gets a client
 * secret; a public one, such as a desktop or browser app, uses PKCE instead. An MCP client
 * registers itself through the same action, with a dynamic origin.
 */
readonly class RegisterOAuthApplication
{
    public function __construct(
        private ClientRepository $clients,
    ) {
    }

    /**
     * @param  non-empty-string  $name
     * @param  list<non-empty-string>  $redirectUris
     * @param  list<string>  $scopes  The scopes the application may request
     * @return array{0: non-empty-string|null, 1: OAuthClient} The secret, shown once (null for a public application), and the client
     */
    public function __invoke(
        string $name,
        array $redirectUris,
        bool $confidential,
        array $scopes,
        bool $firstParty = false,
        ?string $description = null,
        ?string $contactEmail = null,
        ClientOrigin $origin = ClientOrigin::Administrator,
    ): array {
        // A secret outlives the session, so it is never issued while impersonating (D102). Dynamic registration has no session.
        if ($origin === ClientOrigin::Administrator && resolve('impersonate')->isImpersonating()) {
            throw new AuthorizationException('Applications cannot be registered while impersonating.');
        }

        return DB::transaction(function () use ($name, $redirectUris, $confidential, $scopes, $firstParty, $description, $contactEmail, $origin): array {
            /** @var OAuthClient $client */
            $client = $this->clients->createAuthorizationCodeGrantClient($name, array_values($redirectUris), $confidential);

            /** @var non-empty-string|null $secret */
            $secret = $client->plainSecret;

            $client->forceFill([
                'origin' => $origin,
                'scopes' => array_values($scopes),
                'first_party' => $firstParty,
                'description' => $description,
                'contact_email' => $contactEmail,
            ])->save();

            return [$confidential ? $secret : null, $client];
        });
    }
}
