<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;

/**
 * Registers an external application that people can connect to their account through the
 * authorization code flow. A confidential application, such as a web server, gets a client
 * secret; a public one, such as a desktop or browser app, uses PKCE instead. An MCP client
 * registers itself through the same action, with a dynamic origin.
 *
 * An administrator's registration is audited on them: the client's UUID key doesn't fit the
 * audits table. A self-registered MCP client has no one to audit it on.
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
        ?User $registeredBy = null,
    ): array {
        // A secret outlives the session, so it is never issued while impersonating (D102). Dynamic registration has no session.
        if ($origin === ClientOrigin::Administrator && resolve('impersonate')->isImpersonating()) {
            throw new AuthorizationException('Applications cannot be registered while impersonating.');
        }

        return DB::transaction(function () use ($name, $redirectUris, $confidential, $scopes, $firstParty, $description, $contactEmail, $origin, $registeredBy): array {
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

            $registeredBy?->recordCustomAudit('application_registered', [
                'client_id' => $client->getKey(),
                'name' => $client->name,
                'redirect_uris' => $client->redirect_uris,
                'confidential' => $confidential,
                'scopes' => $client->scopes,
                'first_party' => $client->first_party,
            ]);

            return [$confidential ? $secret : null, $client];
        });
    }
}
