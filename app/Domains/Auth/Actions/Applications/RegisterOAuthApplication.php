<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Auth\Enums\ClientOrigin;
use App\Domains\Auth\Models\OAuthClient;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;

/**
 * Registers an external application that people can connect to their account through the
 * authorization code flow. A confidential application, such as a web server, gets a client
 * secret; a public one, such as a desktop or browser app, uses PKCE instead.
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
    ): array {
        return DB::transaction(function () use ($name, $redirectUris, $confidential, $scopes, $firstParty, $description, $contactEmail): array {
            /** @var OAuthClient $client */
            $client = $this->clients->createAuthorizationCodeGrantClient($name, array_values($redirectUris), $confidential);

            /** @var non-empty-string|null $secret */
            $secret = $client->plainSecret;

            $client->forceFill([
                'origin' => ClientOrigin::Administrator,
                'scopes' => array_values($scopes),
                'first_party' => $firstParty,
                'description' => $description,
                'contact_email' => $contactEmail,
            ])->save();

            return [$confidential ? $secret : null, $client];
        });
    }
}
