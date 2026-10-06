<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Laravel\Passport\ClientRepository;

/**
 * Replaces a confidential application's client secret. The old secret stops working at once,
 * so the application must be updated with the new one. Audited on the administrator who did it.
 */
readonly class RegenerateOAuthApplicationSecret
{
    public function __construct(
        private ClientRepository $clients,
    ) {
    }

    /**
     * @return non-empty-string The new secret, shown once
     */
    public function __invoke(OAuthClient $client, User $regeneratedBy): string
    {
        if (! $client->confidential()) {
            throw new InvalidArgumentException('A public application has no client secret.');
        }

        // A secret outlives the session, so it is never issued while impersonating, as with personal access tokens.
        if (resolve('impersonate')->isImpersonating()) {
            throw new AuthorizationException('Application secrets cannot be regenerated while impersonating.');
        }

        $this->clients->regenerateSecret($client);

        /** @var non-empty-string $secret */
        $secret = $client->plainSecret;

        $regeneratedBy->recordCustomAudit('application_secret_regenerated', ['client_id' => $client->getKey(), 'name' => $client->name]);

        return $secret;
    }
}
