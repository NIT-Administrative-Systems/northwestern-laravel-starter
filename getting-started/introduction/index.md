# Introduction

The **Northwestern Laravel Starter** is a Laravel starter kit for [Northwestern University](https://www.northwestern.edu/) applications, with Northwestern sign-in, roles and permissions, auditing, an OAuth-secured API and the University’s branding built in.

## What’s Included

Northwestern Integrations

* **WebSSO/Entra ID** - Single sign-on with Northwestern authentication
* **Directory Search API** - User lookup and demographic sync
* **Wildcard Photos** - ID photo integration with auto-caching
* **EventHub** - Publish to topics and consume events through configured webhooks

Architecture

* **Domain-Driven Design** - Organized by business concerns
* **Action-Based Logic** - Single-responsibility action classes
* **Idempotent Seeding** - Production-safe database seeders
* **Advanced Permissions** - Fine-grained role-based access

Security & Compliance

* **Multi-Auth Methods** - SSO, email verification codes, and OAuth for the API and AI clients
* **Comprehensive Auditing** - Complete trail of all changes
* **Data Encryption** - At-rest and in-transit protection
* **User Impersonation** - Secure troubleshooting capability
* **Data Retention** - Daily pruning of records past their retention period

API Features

* **Service Clients** - Rotation, expiring secrets, IP allowlists and reminders for integrations
* **Personal Access Tokens** - People call the API as themselves, within scopes they choose
* **Connected Applications** - OAuth with a consent screen and PKCE
* **MCP Server** - AI clients use your application’s tools as the person who connected them
* **Request Analytics** - Performance metrics & sampling
* **RFC 9457 Errors** - Standardized error responses
* **Trace IDs** - Correlate logs and requests

User Interface

* **App Panel** - `/app` for your application’s features, with the Account area, announcements, notifications and Contact Support
* **Administration Panel** - `/administration` for users, roles, API access, announcements, audits, and sign-in and API analytics
* **Public Pages** - Landing page, sign-in, changelog, and branded error pages
* **Northwestern Branding** - Filament 5 themed to University styling guidelines

Developer Experience

* **Database Snapshots** - Save and restore state
* **Health Checks** - Monitor platform services
* **Laravel Telescope** - Powerful debugging tool
* **CI/CD Ready** - GitHub Actions workflows included
* **Browser Tests** - Every page the starter ships checked with axe, in light and dark mode, through Pest and Playwright

## Who Should Use This Starter

* **Northwestern IT Projects** - Web applications or APIs that integrate with University services.
* **Administrative Systems** - Applications requiring user management, authentication, audit logging, and policy compliance.
* **API Services** - API-driven services that integrate with other Northwestern systems.

> **Caution**
>
> The starter is built for applications inside Northwestern University’s environment: its sign-in and integrations rely on University services. Outside Northwestern, you can still borrow its architecture and patterns.
