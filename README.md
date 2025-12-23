<p align="center">
    <img width="650px" src="art/readme-lockup.png" alt="Logo lockup for the Northwestern Laravel Starter"/>
</p>

<p align="center">
    <img src="https://img.shields.io/badge/PHP-8.4-blue" alt="PHP Version">
    <img src="https://img.shields.io/badge/Laravel-12.x-red" alt="Laravel Version">
</p>

<hr/>

<div align="center">
  <p>A robust, enterprise-focused Laravel starter kit tailored for <a href="https://www.northwestern.edu" target="_blank">Northwestern University</a> projects. This opinionated project provides everything you need to build secure, scalable, and maintainable web applications or fully API-driven services.</p>

  <table>
    <tr>
      <td align="center">
        <a href="art/ui-preview-1.png" target="_blank">
          <img src="art/ui-preview-1.png" width="500" alt="Authentication screen and homepage UI" />
        </a>
      </td>
      <td align="center">
        <a href="art/ui-preview-2.png" target="_blank">
          <img src="art/ui-preview-2.png" width="500" alt="User profile and table UI" />
        </a>
      </td>
    </tr>
    <tr>
      <td align="center">
        <a href="art/ui-preview-3.png" target="_blank">
          <img src="art/ui-preview-3.png" width="500" alt="API user profile, role creation form, and audit log UI" />
        </a>
      </td>
      <td align="center">
        <a href="art/ui-preview-4.png" target="_blank">
          <img src="art/ui-preview-4.png" width="500" alt="API Request Log and Login Records dashboard UI" />
        </a>
      </td>
    </tr>
  </table>
</div>

## 📘 Overview

Modern web development involves far more than building routes, controllers, and views. Before any business logic can take shape, teams must establish authentication flows, authorization layers, API conventions, auditing, CI/CD pipelines, frontend patterns, monitoring, and a maintainable project structure. These foundational concerns are essential, yet they consume significant time and often lead to duplicated effort across projects.

The **Northwestern Laravel Starter** provides a cohesive solution that takes care of this baseline work up front. It offers a production-ready architecture so development can begin with real features instead of infrastructure setup.

> [!IMPORTANT]
>
> This starter kit is designed primarily for applications built within [Northwestern University](https://www.northwestern.edu)’s ecosystem. If you're outside Northwestern, you may not be able to use the project as-is. However, the architecture, patterns, and modules implemented here may still be valuable as reference material or inspiration. Contributions from the broader community are welcome.

## ✨ Features

### 🏗️ Architectural Foundation

- **Domain-Driven Design**: Code is logically grouped by business concerns for enhanced modularity and maintainability.
- **Action-Based Business Logic**: Single-responsibility action classes encapsulate discrete operations for reusability
  and testability.
- **Flexible Configuration**: Fine-grained settings for authentication methods, API features, Northwestern integrations,
  and application behavior.

### 🔐 Authentication & Authorization

- **Multi-Authentication Methods**: Support for Entra ID SSO, Access Tokens, and passwordless email-based verification codes.
- **Role-Based Access Control**: Fine-grained role and permissions system managed through an intuitive interface.
- **User Impersonation**: Secure ability to troubleshoot user-specific issues and simulate user experiences.

### 🔌 API Features

- **Advanced Access Token Management**: Cryptographically secure tokens with CIDR-based IP restrictions, rotation,
  time-bound validity, and automatic expiration notifications.
- **API Request Logging & Analytics**: Comprehensive request tracking with performance metrics, failure analysis, and
  probabilistic sampling.
- **Request Tracing**: Automatic trace ID propagation for correlation across logs, audits, and error reports.
- **Standardized Error Responses**: [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457.html) compliant machine-readable
  error response formats.

### 📊 Auditing & Compliance

- **Full Audit Trail**: Automatically logs all model changes and user actions with a complete before/after history.
- **Secure Data Handling**: Sensitive information is properly hashed or encrypted to limit the risk of data exposure.

### 🎓 Northwestern Integrations

- **Northwestern Directory**: Just-in-time user provisioning, automatic data synchronization, and monitoring with the
  Northwestern Directory service.
- **EventHub**: Seamless integration for publishing events or registering webhooks with the EventHub system.

### 🎨 Frontend & UX

- **Modular Filament UI**: Ready-to-use administration panel with pre-built tables, forms, and dashboards for managing
  application data.
- **Brand Compliance**: Pre-built components, layouts, and styling that adhere to the University's branding guidelines.
- **Responsive Design**: Consistent user experience across devices with various screen sizes.
- **WCAG 2.1 Accessibility**: Built with accessibility best practices to ensure inclusivity for all users.

### 🧑‍💻 Developer Experience

- **Streamlined Local Development**: Schema-validated database snapshots, configuration validation, and database rebuild
  utilities.
- **High-Performance Testing**: Parallelized PHPUnit execution and end-to-end testing
  with [Cypress](https://www.cypress.io).
- **CI/CD Ready**: Pre-configured GitHub Actions workflows for static analysis, formatting, and automated testing.

### 📈 Monitoring & Operations

- **Health Checks & Monitoring**: Built-in health checks to monitor critical system components.
- **Analytics Dashboards**: Pre-built dashboards for API request metrics and login activity.

## 📋 System Requirements

- [PHP](https://www.php.net/) `^8.4`
- [Node.js](https://nodejs.org/en) `v24.x`
- [pnpm](https://pnpm.io/installation) `^10.0`

## 🚀 Getting Started

Visit the [documentation](https://laravel-starter.entapp.northwestern.edu) for complete installation, configuration, and
usage guides.

## 🤝 Acknowledgements

This starter kit is built upon the contributions of numerous open-source packages. Special thanks to the Laravel
community and [Northwestern University IT](https://www.it.northwestern.edu) for making this project possible.
