<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="art/lockup-dark.svg">
        <img width="650" src="art/lockup-light.svg" alt="Northwestern Laravel Starter">
    </picture>
</p>

<p align="center">
    <img src="https://img.shields.io/badge/PHP-8.5-blue" alt="PHP Version">
    <img src="https://img.shields.io/badge/Laravel-13.x-red" alt="Laravel Version">
    <a href="https://coveralls.io/github/NIT-Administrative-Systems/northwestern-laravel-starter?branch=main"><img src="https://coveralls.io/repos/github/NIT-Administrative-Systems/northwestern-laravel-starter/badge.svg?branch=main" alt="Coverage Status"></a>
</p>

<hr/>

<div align="center">
  <p>An enterprise-focused Laravel starter kit for <a href="https://www.northwestern.edu" target="_blank">Northwestern University</a> projects. This opinionated project provides what you need to build secure, maintainable web applications or API-driven services.</p>

  <table>
    <tr>
      <td align="center">
        <a href="art/ui-preview-1.png" target="_blank">
          <img src="art/ui-preview-1.png" width="500" alt="The public landing page, and the sign-in page on a phone" />
        </a>
      </td>
      <td align="center">
        <a href="art/ui-preview-2.png" target="_blank">
          <img src="art/ui-preview-2.png" width="500" alt="The component gallery, and the app panel with its notifications open" />
        </a>
      </td>
    </tr>
    <tr>
      <td align="center">
        <a href="art/ui-preview-3.png" target="_blank">
          <img src="art/ui-preview-3.png" width="500" alt="The users list, and an audit record of a role assignment" />
        </a>
      </td>
      <td align="center">
        <a href="art/ui-preview-4.png" target="_blank">
          <img src="art/ui-preview-4.png" width="500" alt="Login records, and API request analytics" />
        </a>
      </td>
    </tr>
  </table>
</div>

## Overview

Modern web development extends beyond routes, controllers, and views. Before any business logic can take shape, teams must establish authentication flows, authorization layers, API conventions, auditing, CI/CD pipelines, frontend patterns, monitoring, and a maintainable project structure. These concerns take time and lead to duplicated effort across projects.

The **Northwestern Laravel Starter** handles this baseline work up front with a production-ready architecture, so teams can start building features instead of infrastructure.

> [!IMPORTANT]
>
> This starter kit is designed primarily for applications built within [Northwestern University](https://www.northwestern.edu)’s ecosystem. If you're outside Northwestern, you may not be able to use the project as-is. The architecture and patterns may still be useful as reference material. Contributions from the community are welcome.

## Getting Started

```bash
composer create-project northwestern-sysdev/northwestern-laravel-starter your-project-name
cd your-project-name
```

Visit the [documentation](https://laravel-starter.entapp.northwestern.edu) for complete installation, configuration, and
usage guides.

## Features

### Architectural Foundation

- **Domain-Driven Design**: Code is grouped by business concerns for modularity and maintainability.
- **Action-Based Business Logic**: Single-responsibility action classes encapsulate discrete operations for reusability
  and testability.
- **Flexible Configuration**: Fine-grained settings for authentication methods, API features, Northwestern integrations,
  and application behavior.

### Authentication & Authorization

- **Multi-Authentication Methods**: Support for single sign-on through Entra ID or Online Passport (WebSSO),
  passwordless email-based verification codes, and OAuth credentials for the API through Laravel Passport.
- **Role-Based Access Control**: Fine-grained role and permissions system with a built-in management interface.
- **User Impersonation**: Secure ability to troubleshoot user-specific issues and simulate user experiences.

### API Features

- **OAuth Service Clients**: Client credentials for integrations through Laravel Passport, with short-lived access
  tokens, CIDR-based IP restrictions, rotation without downtime, expiring secrets and expiration notifications.
- **API Request Logging & Analytics**: Request tracking with performance metrics, failure analysis, and
  probabilistic sampling.
- **Request Tracing**: Automatic trace ID propagation for correlation across logs, audits, and error reports.
- **Standardized Error Responses**: [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457.html) compliant machine-readable
  error response formats.

### Auditing & Compliance

- **Full Audit Trail**: Logs all model changes and user actions with before/after history.
- **Secure Data Handling**: Sensitive information is hashed or encrypted to limit data exposure risk.
- **Data Retention**: Configurable retention periods, with records past them pruned daily.

### Northwestern Integrations

- **Northwestern Directory**: Just-in-time user provisioning, automatic data synchronization, and monitoring via the
  Northwestern Directory service.
- **EventHub**: Publish events and register webhooks with the EventHub system.

### Frontend & UX

- **Filament Panels**: An app panel at `/app` for your application's features, with database notifications, and an
  administration panel at `/administration` for users, roles, API clients, audits, and analytics.
- **Public Pages**: A landing page, sign-in, a public changelog, and branded error pages.
- **Contact Support**: A support request form that opens TeamDynamix tickets or sends email.
- **Brand Compliance**: Filament themed to the University's branding guidelines, with the unit footer the Web Style Guide
  requires.
- **Responsive Design**: Consistent user experience across devices with various screen sizes.
- **WCAG 2.1 Accessibility**: Every page the starter ships is checked with axe in Cypress.

### Developer Experience

- **Local Development**: Schema-validated database snapshots, configuration validation, and database rebuild
  utilities.
- **Testing**: Parallel [Pest](https://pestphp.com) tests and end-to-end testing
  with [Cypress](https://www.cypress.io).
- **CI/CD Ready**: Pre-configured GitHub Actions workflows for static analysis, formatting, and automated testing.

### Monitoring & Operations

- **Health Checks & Monitoring**: Built-in health checks to monitor critical system components.
- **Analytics Dashboards**: Pre-built dashboards for API request metrics and login activity.

## Acknowledgements

Numerous open-source packages power this starter kit. Special thanks to the Laravel community and [Northwestern University IT](https://www.it.northwestern.edu).
