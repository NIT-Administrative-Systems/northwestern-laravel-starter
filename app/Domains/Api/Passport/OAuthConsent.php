<?php

declare(strict_types=1);

namespace App\Domains\Api\Passport;

use App\Domains\Api\CredentialAccess;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Api\Http\Middleware\AuthorizeOAuthConsent;
use App\Domains\Api\Models\OAuthClient;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Laravel\Passport\Scope;
use Northwestern\SysDev\Chassis\ValueObjects\OAuthRedirectTarget;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Passport's consent screen: who may connect a client, which scopes the connection gets, and
 * what the screen shows the person before they approve.
 *
 * - {@see AuthorizeOAuthConsent} asks {@see authorize()} on the screen and on its approve and
 *   deny endpoints.
 * - {@see GrantableScopeRepository} narrows the token to {@see grantableScopes()}, and the screen
 *   lists the same scopes, so it never promises more or less than the token carries.
 * - Passport's authorization view renders {@see screen()}.
 */
final readonly class OAuthConsent
{
    public function __construct(
        private CredentialAccess $credentials,
    ) {
        //
    }

    /**
     * Whether `$person` may connect `$client`, as {@see CredentialAccess} decides for issuing the
     * client's kind. Without a client to decide on, as when Passport is about to answer an unknown
     * one itself, impersonating is still refused.
     *
     * @throws AuthorizationException|HttpException
     */
    public function authorize(User $person, ?OAuthClient $client): void
    {
        if ($client instanceof OAuthClient) {
            $this->credentials->decide($person, CredentialOperation::Issue, CredentialKind::of($client), $person)->authorize();
        } elseif (resolve('impersonate')->isImpersonating()) {
            throw new AuthorizationException("Applications can't be connected while impersonating someone.");
        }
    }

    /**
     * The scopes a token for `$client` carries when `$person` approves: those the client may have
     * that the person may grant.
     *
     * @return list<string>
     */
    public function grantableScopes(User $person, OAuthClient $client): array
    {
        $grantable = array_keys($this->credentials->grantableScopes($person, CredentialKind::of($client)));

        return array_values(array_filter($grantable, $client->hasScope(...)));
    }

    /**
     * What the screen shows, from what Passport passes its authorization view:
     *
     * - `scopes`: the requested scopes the token will carry;
     * - `redirectTarget`: where approving sends the person;
     * - `unverified`: the client registered itself, so its name is only its own claim;
     * - `seesAccount`: the token can read the person's account details, which an MCP client's can't.
     *
     * @param  array{client: OAuthClient, user: User, scopes: list<Scope>, request: Request, authToken: string}  $parameters
     * @return array<string, mixed>
     */
    public function screen(array $parameters): array
    {
        ['client' => $client, 'user' => $person, 'request' => $request] = $parameters;
        $grantable = $this->grantableScopes($person, $client);

        return [
            ...$parameters,
            'scopes' => array_values(array_filter($parameters['scopes'], fn (Scope $scope): bool => in_array($scope->id, $grantable, true))),
            // Passport has already matched the redirect URI to one the client registered; without one,
            // it uses the client's only registered URI.
            'redirectTarget' => OAuthRedirectTarget::from($request->string('redirect_uri')->toString() ?: $client->redirect_uris[0]),
            'unverified' => $client->isMcpClient(),
            'seesAccount' => CredentialKind::of($client) !== CredentialKind::McpClient,
        ];
    }
}
