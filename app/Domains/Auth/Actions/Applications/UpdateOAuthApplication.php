<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;

/**
 * Changes an application's details, redirect URIs and allowed scopes. Tokens already issued
 * keep their scopes; new authorizations can only request the scopes now allowed.
 *
 * The change is audited on the administrator who made it, with the values before and after.
 */
readonly class UpdateOAuthApplication
{
    /**
     * @param  non-empty-string  $name
     * @param  list<non-empty-string>  $redirectUris
     * @param  list<string>  $scopes
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

        $updatedBy->recordCustomAudit('application_updated', ['client_id' => $client->getKey(), ...$client->only($audited)], ['client_id' => $client->getKey(), ...$old]);
    }
}
