<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Changes an application's details, redirect URIs and allowed scopes. Tokens already issued
 * keep their scopes; new authorizations can only request the scopes now allowed.
 *
 * The change is audited on the administrator who made it, with the values before and after.
 */
readonly class UpdateOAuthApplication
{
    public function __construct(
        private CredentialAccess $credentials,
    ) {
    }

    /**
     * @param  non-empty-string  $name
     * @param  list<non-empty-string>  $redirectUris
     * @param  list<string>  $scopes
     *
     * @throws AuthorizationException
     */
    public function __invoke(
        OAuthClient $client,
        string $name,
        array $redirectUris,
        array $scopes,
        bool $firstParty,
        ?string $description,
        ?string $contactEmail,
        User $updatedBy,
    ): void {
        $this->credentials->decide($updatedBy, CredentialOperation::Modify, CredentialKind::ConnectedApplication, null)->authorize();

        if ($client->getAttributes()['revoked']) {
            throw new InvalidArgumentException('A revoked application cannot be changed.');
        }

        $audited = ['name', 'redirect_uris', 'scopes', 'first_party', 'description', 'contact_email'];
        $old = $client->only($audited);

        $client->forceFill([
            'name' => $name,
            'redirect_uris' => array_values($redirectUris),
            'scopes' => array_values($scopes),
            'first_party' => $firstParty,
            'description' => $description,
            'contact_email' => $contactEmail,
        ])->save();

        $updatedBy->recordAuditEvent(AuditEvent::ApplicationUpdated, ['client_id' => $client->getKey(), ...$client->only($audited)], ['client_id' => $client->getKey(), ...$old]);
    }
}
