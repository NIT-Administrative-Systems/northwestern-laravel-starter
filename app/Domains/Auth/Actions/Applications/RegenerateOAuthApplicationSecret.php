<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
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
        private CredentialAccess $credentials,
    ) {
    }

    /**
     * @return non-empty-string The new secret, shown once
     *
     * @throws AuthorizationException
     */
    public function __invoke(OAuthClient $client, User $regeneratedBy): string
    {
        $this->credentials->decide($regeneratedBy, CredentialOperation::Modify, CredentialKind::ConnectedApplication, null)->authorize();

        if (! $client->confidential()) {
            throw new InvalidArgumentException('A public application has no client secret.');
        }

        if ($client->getAttributes()['revoked']) {
            throw new InvalidArgumentException('A revoked application cannot get a new secret.');
        }

        $this->clients->regenerateSecret($client);

        /** @var non-empty-string $secret */
        $secret = $client->plainSecret;

        $regeneratedBy->recordCustomAudit('application_secret_regenerated', ['client_id' => $client->getKey(), 'name' => $client->name]);

        return $secret;
    }
}
