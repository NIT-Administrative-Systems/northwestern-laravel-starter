# WebSSO / Entra ID

Northwestern users authenticate via single sign-on. The starter supports two SSO providers through the [`northwestern-sysdev/laravel-soa`](https://github.com/NIT-Administrative-Systems/SysDev-laravel-soa) package’s `WebSSOAuthentication` trait.

Providers:

* **Microsoft Entra ID** (formerly Azure AD) - OAuth2 authorization code flow directly to Microsoft
* **Online Passport** (agentless WebSSO) - Cookie-based SSO via OpenAM/ForgeRock, validated through Apigee

The starter **auto-detects** which provider to use based on your credentials (`SsoProvider::configured()`). Online Passport is used when `WEBSSO_API_KEY` is set or `WEBSSO_STRATEGY` is `forgerock-direct`; otherwise Entra ID is used when both `AZURE_CLIENT_ID` and `AZURE_CLIENT_SECRET` are set. Only that provider’s routes are registered, and sign-in, logout and `config:validate` all use it. When neither is configured, the sign-in page has no NetID button.

SSO is optional in the `local` environment, where the sign-in page offers **Sign In As** for the seeded demo users and `config:validate` skips the SSO check when no provider is configured. See [Signing In Locally](https://laravel-starter.entapp.northwestern.edu/features/authentication/#signing-in-locally). Deployed environments always need a provider.

For a higher-level overview of all authentication methods, see the [Authentication](https://laravel-starter.entapp.northwestern.edu/features/authentication/) documentation.

## How It Works (Entra ID)

1. **User clicks “Sign In with NetID”**

   The application redirects to Microsoft’s login endpoint via `WebSSOController@oauthRedirect`. The OAuth2 authorization code flow goes directly to `login.microsoftonline.com`, not through Apigee or ForgeRock.

2. **User authenticates with Microsoft**

   User enters their credentials.

3. **Callback with token**

   Microsoft redirects back to `/auth/azure-ad/callback` via POST with an authorization code. CSRF middleware is excluded for this route since the request originates from the identity provider. The `laravel-soa` package exchanges the code for tokens and validates the ID token JWT using Microsoft’s signing keys.

4. **Directory Search lookup**

   `WebSSOController::findUserByNetID()` calls `FindOrUpdateUserFromDirectory` to provision or update the user from Northwestern’s directory. This includes a 3-attempt retry with 500ms delays. If all retries fail, the user sees the 503 page (see [Error Handling](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/websso/#error-handling)).

5. **Session established**

   The `laravel-soa` trait logs the user in, then calls the starter’s `authenticated()` hook, which finishes with `SignIn::complete()`, as every sign-in method does: a new session and CSRF token, a `UserLoginRecord`, and `/`, where `HomeController` sends them on to the app panel, unless they were headed for another page first. Single sign-on isn’t remembered past the session, since signing in again is usually silent.

## How It Works (Online Passport)

1. **User clicks “Sign In with NetID”**

   The application redirects to ForgeRock’s login page via `WebSSOController@login`. The user is sent to Northwestern’s Online Passport login portal.

2. **User authenticates with ForgeRock**

   User enters their NetID credentials (and completes MFA if Duo is enabled).

3. **Cookie validation**

   After authentication, ForgeRock sets a `nusso` session cookie. The `laravel-soa` package validates this cookie by calling Northwestern’s agentless WebSSO API (via Apigee or directly against ForgeRock, depending on the configured strategy).

4. **Directory Search lookup**

   `WebSSOController::findUserByNetID()` calls `FindOrUpdateUserFromDirectory` to provision or update the user, identical to the Entra ID flow.

5. **Session established**

   The `laravel-soa` trait logs the user in, then calls the starter’s `authenticated()` hook, which finishes with `SignIn::complete()`, as every sign-in method does: a new session and CSRF token, a `UserLoginRecord`, and `/`, where `HomeController` sends them on to the app panel, unless they were headed for another page first. Single sign-on isn’t remembered past the session, since signing in again is usually silent.

***

## Routes

Routes are defined in `routes/auth.php`. The Entra ID and Online Passport routes are registered only when that provider is configured; the shared logout route is always registered.

### Entra ID Routes

| Method | URI                           | Controller                       | Name                   |
| ------ | ----------------------------- | -------------------------------- | ---------------------- |
| `GET`  | `/auth/azure-ad/redirect`     | `WebSSOController@oauthRedirect` | `login-oauth-redirect` |
| `POST` | `/auth/azure-ad/callback`     | `WebSSOController@oauthCallback` | `login-oauth-callback` |
| `GET`  | `/auth/azure-ad/oauth-logout` | `WebSSOController@oauthLogout`   | `login-oauth-logout`   |

### Online Passport Routes

| Method | URI                   | Controller                | Name                  |
| ------ | --------------------- | ------------------------- | --------------------- |
| `GET`  | `/auth/websso/login`  | `WebSSOController@login`  | `login-websso`        |
| `GET`  | `/auth/websso/logout` | `WebSSOController@logout` | `login-websso-logout` |

### Shared Route

| Method | URI            | Controller                  | Name     |
| ------ | -------------- | --------------------------- | -------- |
| `POST` | `/auth/logout` | `LogoutSelectionController` | `logout` |

`LogoutSelectionController` asks `SignIn::signOut()`, which sends SSO users to the configured provider’s logout route. Local users, and SSO users when no provider is configured, are signed out locally.

***

## Logout

### Entra ID Logout

The `oauthLogout()` method handles SSO session cleanup:

* **Outside CI** - Delegates to the `laravel-soa` trait’s `oauthLogout()` method, which clears the local session and then redirects to Entra ID’s logout endpoint.
* **CI environment** - Skips the Entra redirect and performs a local-only logout to avoid external service dependencies.

### Online Passport Logout

The `logout()` method (from the `WebSSOAuthentication` trait) redirects the user to ForgeRock’s logout endpoint to clear the `nusso` session cookie. The local session is invalidated before the redirect.

***

## Error Handling

If the Directory Search lookup fails after 3 retries during the SSO callback, the controller throws a `ServiceDownError` for `ExternalService::DirectorySearch` (`directory-search`). The exception renders itself: browser requests get the 503 page (`resources/views/errors/503.blade.php`), and API or JSON requests get a Problem Details 503 response with `Retry-After: 60`. The error is still reported to Sentry.

***

## Entra ID Configuration

Environment Variables

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/websso/#prop-azure-client-id)`AZURE_CLIENT_ID`Required

Entra ID application client ID

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/websso/#prop-azure-client-secret)`AZURE_CLIENT_SECRET`Required

Entra ID application client secret

> **Tip**
>
> Entra ID requires registering the application in Azure’s control panel. You’ll need to generate a client ID and secret, and register the callback URI. The callback must use HTTPS. The starter builds the callback URL from the `login-oauth-callback` route (`https://your-app.northwestern.edu/auth/azure-ad/callback`), so there is no redirect URI setting. `php artisan websso:callback` prints the URL to register.

***

## Online Passport Configuration

Setting `WEBSSO_API_KEY` (or using `WEBSSO_STRATEGY=forgerock-direct`) switches the starter from Entra ID to Online Passport. The login and logout buttons will automatically route through ForgeRock instead of Microsoft.

Environment Variables

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/websso/#prop-websso-strategy)`WEBSSO_STRATEGY``apigee`

SSO strategy (`apigee` or `forgerock-direct`)

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/websso/#prop-websso-url-base)`WEBSSO_URL_BASE``https://uat-nusso.it.northwestern.edu`

ForgeRock base URL

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/websso/#prop-websso-api-url-base)`WEBSSO_API_URL_BASE``https://northwestern-prod.apigee.net/agentless-websso`

Agentless WebSSO API URL (Apigee)

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/websso/#prop-websso-api-key)`WEBSSO_API_KEY`Required

Apigee API key for agentless WebSSO

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/websso/#prop-websso-realm)`WEBSSO_REALM``northwestern`

WebSSO realm

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/websso/#prop-websso-cookie-name)`WEBSSO_COOKIE_NAME``nusso`

WebSSO session cookie name

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/websso/#prop-duo-enabled)`DUO_ENABLED``false`

Enable Duo MFA (sets auth tree to `ldap-and-duo`)

> **Note**
>
> The `apigee` strategy routes ForgeRock cookie validation through Northwestern’s Apigee gateway. The `forgerock-direct` strategy validates cookies directly against the ForgeRock server. Both are for the Online Passport flow; Entra ID does not use either strategy.

See the [laravel-soa documentation](https://nit-administrative-systems.github.io/SysDev-laravel-soa/) for full details on configuring Online Passport.
