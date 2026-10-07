# Overview

## Northwestern Laravel Starter API 1.0.0

The API endpoints the Northwestern Laravel Starter ships. Applications add their own beside them.

Information

* OpenAPI version: `3.0.0`

## Operations

GET

[/api/v1/me](https://laravel-starter.entapp.northwestern.edu/api/operations/get-user-details/)

## Authentication

### oauth2

OAuth 2.0. A service integration uses the client credentials flow with a service client created in Administration, and its token acts as the client’s API user. An application registered in Administration uses the authorization code flow with PKCE, and its token acts as the person who connected it, within the scopes they approved.

**Security scheme type:&#x20;**&#x6F;auth2

**Flow type:&#x20;**&#x63;lientCredentials

**Token URL:&#x20;**[/oauth/token](https://laravel-starter.entapp.northwestern.edu/oauth/token)

**Scopes:**

* view-users - View everyone's profiles and details.

**Flow type:&#x20;**&#x61;uthorizationCode

**Authorization URL:&#x20;**[/oauth/authorize](https://laravel-starter.entapp.northwestern.edu/oauth/authorize)

**Token URL:&#x20;**[/oauth/token](https://laravel-starter.entapp.northwestern.edu/oauth/token)

**Refresh URL:&#x20;**[/oauth/token](https://laravel-starter.entapp.northwestern.edu/oauth/token)

**Scopes:**

* view-users - View everyone's profiles and details.

### bearerToken

A personal access token created on the Account page, or an access token from either OAuth flow, sent as a bearer token in the Authorization header.

**Security scheme type:&#x20;**&#x68;ttp
