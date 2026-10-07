<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Core\Enums\AuditEvent;
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
        private CredentialAccess $credentials,
    ) {
    }

    /**
     * @param  non-empty-string  $name
     * @param  list<non-empty-string>  $redirectUris
     * @param  list<string>  $scopes  The scopes the application may request
     * @param  User|null  $registeredBy  The administrator; null for a seeder or a self-registered MCP client
     * @return array{0: non-empty-string|null, 1: OAuthClient} The secret, shown once (null for a public application), and the client
     *
     * @throws AuthorizationException
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
        // Dynamic registration is anonymous: the MCP routes decide whether it's open.
        if ($origin === ClientOrigin::Administrator) {
            $this->credentials->decide($registeredBy, CredentialOperation::Issue, CredentialKind::ConnectedApplication, null)->authorize();
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

            $registeredBy?->recordAuditEvent(AuditEvent::ApplicationRegistered, [
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
