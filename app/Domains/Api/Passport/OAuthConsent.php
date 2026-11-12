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
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Scope;
use Northwestern\SysDev\Chassis\ValueObjects\OAuthRedirectTarget;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Passport's consent screen: who may connect a client, which scopes the
 * token gets, and what the screen shows a person before they approve.
 *
 * {@see AuthorizeOAuthConsent} asks {@see authorize()} on the consent screen
 * and on its approve and deny endpoints. {@see GrantableScopeRepository}
 * narrows the token to {@see grantableScopes()}, and the screen lists
 * the same scopes, so it never promises more or less than the token
 * carries. Passport's authorization view renders {@see screen()}.
 */
final readonly class OAuthConsent
{
    public function __construct(
        private CredentialAccess $credentials,
    ) {
        //
    }

    /**
     * Authorize `$person` to connect `$client`, or throw.
     *
     * {@see CredentialAccess} decides, as when issuing a client of this kind.
     * An MCP client must also ask for `mcp:use`, since a token without it
     * would be good for neither the MCP server nor the REST API. Even
     * without a client to decide on, as when Passport is about to
     * answer an unknown one itself, impersonating is refused.
     *
     * @param  list<string>  $requestedScopes  The scopes the authorization request asks for
     *
     * @throws AuthorizationException|HttpException
     */
    public function authorize(User $person, ?OAuthClient $client, array $requestedScopes = []): void
    {
        if ($client instanceof OAuthClient) {
            $kind = CredentialKind::of($client);
            $this->credentials->decide($person, CredentialOperation::Issue, $kind, $person)->authorize();

            if ($kind === CredentialKind::McpClient && ! in_array(Registrar::OAUTH_SCOPE, $requestedScopes, true)) {
                throw new AuthorizationException('This AI client didn\'t ask to use this application\'s tools, so it can\'t connect.');
            }
        } elseif (resolve('impersonate')->isImpersonating()) {
            throw new AuthorizationException("Applications can't be connected while impersonating someone.");
        }
    }

    /**
     * Get the scopes a token for `$client` carries when `$person` approves
     * it: the ones the client may have that the person may also grant.
     *
     * @return list<string>
     */
    public function grantableScopes(User $person, OAuthClient $client): array
    {
        $grantable = array_keys($this->credentials->grantableScopes($person, CredentialKind::of($client)));

        return array_values(array_filter($grantable, $client->hasScope(...)));
    }

    /**
     * Get what the consent screen shows, from what Passport passes its view.
     *
     * Besides Passport's own values, `scopes` is the requested scopes the token
     * will carry, and `redirectTarget` is where approving sends the person.
     *
     * `verifiedBy` is the host whose client metadata document named
     * an MCP client, such as `claude.ai`; `unverified` means the
     * client registered itself, so its name is only a claim.
     *
     * `seesAccount` says whether the token can read the person's account details.
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
            // Passport has already matched the redirect URI to one the client
            // registered; without one, it uses the only URI the client has.
            'redirectTarget' => OAuthRedirectTarget::from($request->string('redirect_uri')->toString() ?: $client->redirect_uris[0]),
            'verifiedBy' => $client->verifiedBy(),
            'unverified' => $client->isMcpClient() && $client->verifiedBy() === null,
            'seesAccount' => CredentialKind::of($client) !== CredentialKind::McpClient,
        ];
    }
}
