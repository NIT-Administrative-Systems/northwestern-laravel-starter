# Overview

The starter integrates with Northwestern-specific services for identity management, user provisioning, and lifecycle events. Support tickets go to TeamDynamix, covered in [Support Tickets](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/). The integrations on this page use the [`northwestern-sysdev/laravel-soa`](https://github.com/NIT-Administrative-Systems/SysDev-laravel-soa) package, which provides Laravel bindings for Northwestern’s SOA APIs.

## Integrations

Directory Search

Look up Northwestern users by NetID, email, or employee ID via the LDAP-backed Directory Search API. Used during SSO login for automatic user provisioning and profile synchronization.

[Directory Search →](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/directory-search/)

WebSSO / Entra ID

Single sign-on authentication for Northwestern users. Ships with Microsoft Entra ID (Azure AD) by default, with support for Online Passport (agentless WebSSO via ForgeRock) as an alternative.

[WebSSO →](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/websso/)

EventHub

Consume event messages from Northwestern’s EventHub enterprise messaging platform. The starter listens for NetID lifecycle events to automatically deactivate users.

[EventHub →](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/eventhub/)

Wildcard Photos

Download Northwestern Wildcard ID card photos from Directory Search and serve them via presigned S3 URLs.

[Wildcard Photos →](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/wildcard-photos/)

***

## The `laravel-soa` Package

The [`northwestern-sysdev/laravel-soa`](https://github.com/NIT-Administrative-Systems/SysDev-laravel-soa) package provides:

* **Directory Search** - LDAP user lookup by NetID, email, or employee ID
* **WebSSO Authentication** - Entra ID (Azure AD) OAuth2 flow and Online Passport (agentless WebSSO via ForgeRock/Apigee)
* **EventHub** - Webhook registration, HMAC verification middleware (`eventhub_hmac`), and queue integration

Full package documentation is available at [nit-administrative-systems.github.io/SysDev-laravel-soa](https://nit-administrative-systems.github.io/SysDev-laravel-soa/).

### Configuration

All `laravel-soa` settings live in `config/nusoa.php`. The starter ships its own copy of this file, which adds `directorySearch.healthCheckNetid` for the health check and `eventHub.mock` (on by default when `APP_ENV=local`) to the package’s version.

```bash
# .env — Core API credentials
DIRECTORY_SEARCH_URL=https://northwestern-prod.apigee.net/directory-search
DIRECTORY_SEARCH_API_KEY=your-api-key


# Entra ID (default auth method)
AZURE_CLIENT_ID=your-client-id
AZURE_CLIENT_SECRET=your-client-secret
# No redirect URI setting: the callback URL comes from the login-oauth-callback route


EVENT_HUB_BASE_URL=https://northwestern-prod.apigee.net
EVENT_HUB_API_KEY=your-api-key
EVENT_HUB_HMAC_VERIFICATION_SHARED_SECRET=your-shared-secret
```
