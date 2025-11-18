<p align="center">
    <picture>
        <source
            width="650px"
            media="(prefers-color-scheme: dark)"
            srcset="art/readme-lockup-dark.svg"
        >      
        <img
            width="650px"
            src="art/readme-lockup-light.svg"
            alt="Northwestern Laravel Starter"/>
    </picture>
</p>

<p align="center">
    <a href="https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/releases/">
        <img src="https://img.shields.io/github/release/NIT-Administrative-Systems/northwestern-laravel-starter.svg" alt="Latest release" />
    </a>
    <br />
    <img src="https://img.shields.io/badge/PHP-8.4-blue" alt="PHP Version">
    <img src="https://img.shields.io/badge/Laravel-12.x-red" alt="Laravel Version">
</p>

<hr/>

A robust, enterprise-ready Laravel starter kit tailored specifically for [Northwestern University](https://www.northwestern.edu/) projects. This opinionated framework provides everything you need to build secure, scalable, and maintainable web applications. Skip the boilerplate setup and focus on building features that matter to your users.

## ✨ Features

- **🔒 Authentication & Authorization**
    - **Microsoft Entra ID SSO** - Seamless integration with Northwestern's identity provider
    - **Role-Based Access Control** - Granular permissions system with intuitive management interface
    - **User Impersonation** - Secure capability to troubleshoot user-specific issues and simulate user experiences
- **📊 Data Management & Logging**
    - **Audit Logging** - Complete tracking of model changes and an intuitive dashboard for viewing logs
    - **Northwestern Directory Integration** - Automatic user provisioning and synchronization with Northwestern's directory service
    - **Error Monitoring** - Optional [Sentry](https://sentry.io/) integration for real-time error monitoring and alerting
- **🎨 Frontend & UX**
    - **Northwestern Brand Compliance** - Pre-build components and layouts aligned with Northwestern brand guidelines
    - **Responsive Design System** - Consistent experience across devices with various viewport sizes
    - **WCAG 2.1 Accessibility** - Inclusive interfaces to support assistive technologies
- **🛠️ Developer Productivity**
    - **Domain-Driven Architecture** - Scalable and maintainable codebase organization
    - **High-Performance Testing** - Parallel PHPUnit execution and E2E testing with [Cypress](https://www.cypress.io/)
    - **CI/CD Ready** - Pre-configured quality gates with [Rector](https://github.com/rectorphp/rector), [Laravel Pint](https://github.com/laravel/pint), [Prettier](https://prettier.io/), [PHPStan](https://phpstan.org/), [PHPUnit](https://phpunit.de/), and [Cypress](https://www.cypress.io/)
- **➕ Additional Features** – See the [documentation](https://nit-administrative-systems.github.io/northwestern-laravel-starter) for a list of all available features

## 📋 System Requirements

- [PHP](https://www.php.net/) `^8.4`
- [Node.js](https://nodejs.org/en) `v24.x`
- [pnpm](https://pnpm.io/installation) `^10.0`

## 📘 Documentation

For more details on using and customizing this starter kit, please visit the Northwestern Laravel Starter documentation. The documentation covers additional features, configuration options, and other in-depth topics to help you make the most of this project.

## 🤝 Acknowledgements

This starter kit is built upon the contributions of numerous open-source packages. Special thanks to the Laravel community and Northwestern University IT for making this project possible.
