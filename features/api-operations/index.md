# API Operations

The [API System](https://laravel-starter.entapp.northwestern.edu/features/api/) page explains the credentials the starter issues. This page is for running them: the keys they depend on, what each rotation invalidates, the tasks that clean them up, and the situations in which the starter refuses to issue or use them.

## Signing Keys

Passport signs every access token with an RSA key pair.

* **Deployed environments** set the keys in `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`. Generate a pair with `openssl` in a temporary directory, store the contents of the two files in the environment’s secrets, and delete them. See [OAuth Signing Keys](https://laravel-starter.entapp.northwestern.edu/guides/deployment/#oauth-signing-keys).
* **Locally**, `php artisan db:rebuild` generates key files in `storage/` when they don’t exist.
* **Each environment** has its own pair, shared by all of its instances.

> **Rotating the signing keys revokes every token**
>
> Every access token is signed with the private key. Replacing the key pair makes every outstanding access token invalid at once. Integrations recover by requesting new access tokens with their existing client credentials, and applications and AI clients by refreshing or reconnecting. Personal access tokens can’t be reissued: they stop working for good, still listed as active, and their owners have to create new ones.

## What Each Rotation Invalidates

| Rotating                                        | Stops working                                                                      | Keeps working                              |
| ----------------------------------------------- | ---------------------------------------------------------------------------------- | ------------------------------------------ |
| The signing keys                                | Every access token, including personal access tokens                               | Client secrets, refresh tokens             |
| `APP_KEY`                                       | Refresh tokens and unredeemed authorization codes, which Passport encrypts with it | Access tokens, client secrets              |
| A service client (**Rotate** in Administration) | Nothing at first: the old client works until you revoke it                         | Both clients, until the old one is revoked |
| An application’s secret (**Regenerate Secret**) | The old secret, at once                                                            | Access and refresh tokens already issued   |

After rotating `APP_KEY`, applications and AI clients can’t refresh their tokens and have to send people through the consent screen again once their access tokens expire.

## Cleanup and Revocation

The scheduler keeps the credential tables accurate. See [Scheduled Tasks](https://laravel-starter.entapp.northwestern.edu/reference/commands/#scheduled-tasks) for the cadences.

* **`passport:purge --expired --hours=744`** deletes tokens and authorization codes once they expired more than 31 days ago, revoked or not. Revoked access tokens are kept that long on purpose: a refresh token is found through its access token when access is revoked, so an access token has to outlive its refresh token’s 30-day lifetime.
* **`oauth:revoke-ineligible`** marks revoked every personal access token, connection and service client its holder can no longer use: a deactivated or deleted account, or a person who lost `CreatePersonalAccessTokens` or `UseMcp`. A feature that is turned off isn’t a reason: its credentials wait for it to be turned back on. The API already refuses all of them on every request; this makes the Account and Administration pages say so. Each is revoked through its action, so it appears in the holder’s audit history.
* **`mcp:prune-clients`** deletes self-registered MCP clients nobody connected within a day and revokes those nobody has used for 90 days.
* **`model:prune`** deletes API request logs after `API_REQUEST_LOG_RETENTION_DAYS` (90).

The starter creates Passport’s personal access client the first time someone creates a personal access token. It needs no setup and isn’t listed with the applications.

## When the Starter Refuses

Every rule for who may see, issue, change, revoke or use a credential is decided in one place, `App\Domains\Api\CredentialAccess`. Each action that issues, changes or revokes a credential takes the person acting and asks it, and so do the middleware that authenticates tokens, the consent screen and `oauth:revoke-ineligible`. To change a rule, change it there, and add a case to `tests/Feature/Domains/Api/CredentialAccessTest.php`. Pages, resources and actions ask it through the `App\Domains\Api\Concerns\AuthorizesCredentials` trait, which answers for the signed-in person: use it in your own pages too, rather than checking permissions, feature switches or impersonation yourself.

### While Impersonating

An administrator impersonating someone acts with that person’s permissions, but can’t create, change or take away any credential: credentials only change under the name of the person changing them. The server refuses:

* creating or revoking the person’s personal access tokens;
* approving an application on the consent screen, or disconnecting one;
* creating, rotating, editing or revoking a service client, including creating an API user with one;
* registering, editing or revoking an application, regenerating its secret, or revoking an MCP client.

Changing the person’s preferences is refused too.

Administrators manage a person’s tokens and connections from the person’s page in Administration instead, where each action is recorded in the person’s audit history.

### Under Environment Lockdown

[Environment lockdown](https://laravel-starter.entapp.northwestern.edu/getting-started/initial-customization/#5-environment-lockdown) covers the consent screen: a person who can’t use the application can’t connect one to it either. Bearer requests to the API aren’t affected by lockdown.

### With a Feature Off

* **`API_ENABLED=false`** makes every route under `/api` respond 404. Nobody can create a personal access token or connect an application (the consent screen answers 404), and `/oauth/token` refuses every grant to service clients and connected applications with `unauthorized_client`.
* **`MCP_ENABLED=false`** makes the MCP server, its discovery documents and dynamic registration respond 404, the consent screen answers 404 for MCP clients, and `/oauth/token` refuses to refresh their tokens.

Nothing is revoked when a feature is turned off. Turning it back on brings every credential back. Administrators can still see and revoke credentials of that kind in Administration, to shut a feature down cleanly, but can’t create or change them.

## Behind a Proxy

A service client’s IP allowlist checks the address Laravel reports for the request (`$request->ip()`), and the starter trusts no proxy headers. When a load balancer or CDN sits in front of the application, configure trusted proxies (`$middleware->trustProxies()` in `bootstrap/app.php`) so Laravel reads the caller’s address from `X-Forwarded-For`; otherwise every request appears to come from the proxy.

When integrations come [through Apigee](https://laravel-starter.entapp.northwestern.edu/features/api/#through-apigee), the application sees Apigee’s address, not the integration’s. Restrict callers in Apigee, or allowlist Apigee’s addresses on the client.

## Error Responses

The REST API under `/api` answers errors as [Problem Details](https://www.rfc-editor.org/rfc/rfc9457) (`application/problem+json`). Passport’s `/oauth/*` endpoints and the MCP server keep their own protocols’ error bodies, which OAuth and MCP clients expect.
