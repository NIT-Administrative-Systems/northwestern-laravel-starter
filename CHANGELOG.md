# Changelog

All notable changes to the [Northwestern Laravel Starter](https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

Version 3 removes the Bootstrap user interface. Every page now uses one stack, Filament (Livewire, Alpine and Tailwind CSS), with Northwestern branding from `northwestern-sysdev/northwestern-filament-theme` and Department Templates 4.0. Version 3 is for new applications; applications built on version 2 stay on version 2.

### Removed

- `northwestern-sysdev/northwestern-laravel-ui`, its `northwestern::` layouts and `config/northwestern-theme.php`. Unit details, the lockup and footer links are configured in `config/northwestern-filament-theme.php` (`NU_UNIT_*`, `NU_LOCKUP`).
- Bootstrap, Sass, Font Awesome, Tom Select, `clipboard` and `axios`, with `resources/sass`, `resources/js/app.js`, `resources/js/bootstrap.js` and `resources/js/utils.js`.
- The Blade component library (`<x-select>`, `<x-clipboard>`, `<x-breadcrumbs>`, `<x-tooltip>`, `<x-wildcard-photo>`, `<x-not-yet-implemented>`), `AsyncSelectOptionsRequest` and the Bootstrap navigation.
- `local-auth.redirect_after_login` (`LOCAL_AUTH_REDIRECT_AFTER_LOGIN`); `HomeController::destinationFor()` decides where signed-in users go.
- GlobalAlert; Filament's impersonation banner shows who is being impersonated.
- `InjectLivewireAssets`; Livewire injects its own assets.
- The bespoke API access tokens: the `AccessToken` model and `access_tokens` table, the `/api/v1/me/tokens` endpoints, `AuthenticatesAccessTokens`, `IssueAccessToken` and `RotateAccessToken`, `access-tokens:notify-expiration`, and `API_DEMO_USER_ACCESS_TOKEN`. No API endpoint creates credentials any more.

### Added

- An `app` panel at `/app`, the default panel, for each application's features. Any signed-in user can open it. Its sidebar is for the application's features (the starter ships Filament's dashboard, and the component gallery outside production); its top bar has a Help menu (Changelog, Contact Support, and documentation when `SUPPORT_DOCUMENTATION_URL` is set) and an "Administration" user-menu item for users who can open that panel.
- Filament sign-in pages: `/app/login` lists the configured methods and goes straight to single sign-on when it is the only one, and `/app/login/email` requests and verifies a login code on one page. The login code logic moved into the `RequestLoginCode` and `AuthenticateWithLoginCode` actions.
- A public layout (`<x-layouts.public>`) for pages outside the panels. It runs in the app panel's context so Filament's Blade components work, and it is light only. Its header (`<x-site-header>`) matches the panels' top bar and is shared by the sign-in, lockdown and error pages.
- A landing page at `/` for guests. `HomeController::destinationFor()` decides where signed-in users go, by default the app panel.
- Database notifications in the app panel: a bell in the top bar, polled every 30 seconds.
- Retention periods for audit logs (`AUDIT_RETENTION_DAYS`, kept by default), sign-in records (`LOGIN_RECORD_RETENTION_DAYS`, 365 days) and impersonation logs (`IMPERSONATION_LOG_RETENTION_DAYS`, kept by default), through the new `PrunesAfterRetentionPeriod` trait.
- Accessibility checks with axe for every page the starter ships, in `cypress/e2e/accessibility.cy.ts`. `cy.checkAxeViolations()` accepts selectors to exclude.
- `AGENTS.md`, a guide for coding agents working in the repository.
- "Sign in as" in the `local` environment: the sign-in page lists the seeded demo users, and `/app/login/as/{username}` signs in as one, so a local environment, worktree or agent needs no SSO, email or credentials. The route exists only when `APP_ENV=local`.
- A "Your account" widget on the app panel's dashboard: a greeting with the user's previous sign-in, their roles, and links to Contact Support and the documentation when configured.
- An Account area at `/app/account`, reached from the user menu and the "Your account" widget. Profile shows the user's details (from the Northwestern Directory for NetID users), how they sign in, their recent sign-ins and their roles, read-only. Preferences lets them choose their timezone; an administrator impersonating them can see it but not save it.
- A `users.preferences` JSON column read through `App\Domains\User\Data\UserPreferences`, for applications to add their own per-user settings as typed properties with defaults. The base class, `App\Domains\Core\Data\Preferences`, can cast a JSON column on any model.
- API credentials through Laravel Passport. Each API user owns service clients: an integration exchanges a client's ID and secret at `POST /oauth/token` (client credentials) for an access token that lasts one hour and acts as the API user. Clients have an IP allowlist, a secret that expires within a year, and rotation that keeps the old client working until it is revoked; revoking a client revokes the tokens it holds. Administrators manage them on an API user's **Clients** tab. Authentication and request logging use chassis's `AuthenticatesPassportTokens` and `LogsPassportRequests`.
- `oauth-clients:notify-secret-expiration`, which emails an API user's contact address before a client secret expires (`API_CLIENT_SECRET_EXPIRATION_NOTIFICATIONS_ENABLED`), and a daily `passport:purge`.
- Passport signing keys from `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`; locally, `db:rebuild` generates key files and seeds the demo API user's client with a fixed ID and secret, which it prints.
- A per-IP API limit before authentication (`RATE_LIMIT_API_PER_IP_PER_MINUTE`), also covering Passport's OAuth endpoints, alongside the per-client limit after authentication (`RATE_LIMIT_API_PER_MINUTE`).
- `config/cors.php`: no browser origin may call the API unless it is listed in `CORS_ALLOWED_ORIGINS`.
- `ViewApiRequestLogs`, the permission to view API request logs and charts.
- A component gallery at `/app/gallery`, in the app panel's sidebar outside production, showing Filament's components in the Northwestern theme. Delete it when you no longer need it.
- An error layout (`<x-layouts.error>`) that renders without Filament, auth or the database, so the 500, 503 and database-paused pages work when the database is down. The client error pages (401, 403, 404, 419, 429) render on the public layout with the full header, and a fallback route lets the not-found page know who is signed in.
- Browser Sentry on every page through `<x-sentry-browser />` and `resources/js/sentry.js`, relayed through chassis's DSN-validating `SentryTunnelController`. The new `sentry.tracing.browser` setting (`SENTRY_ENABLE_APM_FOR_JS`) enables browser tracing.
- A branded mail theme. Every email's footer shows the unit's contact details and the Accessibility and Privacy Statement links the university requires.

### Changed

- The app panel is the default panel; the administration panel is at `/administration`.
- Sign-in is in the app panel, at `/app/login` and `/app/login/email`, and the environment lockdown page is at `/app/access-restricted`.
- The changelog and Contact Support pages were rebuilt on the public layout and the app panel; Contact Support is at `/app/support/contact`. The changelog's URLs and RSS feed are unchanged.
- Every retention setting lives under `platform.retention`, including those for login challenges and API request logs.
- SSO and Directory Search are optional in the `local` environment: `config:validate` and the Directory Search health check skip them when they aren't configured, the administration panel's Add NU User says Directory Search isn't configured, and Force Sync is hidden without it.
- Locally, generated URLs and the session cookie's `secure` flag follow `APP_URL`'s scheme, so a worktree or agent can serve the app over plain HTTP on any port. Deployed environments still force HTTPS. `.env.example` no longer sets `SESSION_SECURE_COOKIE`.
- `db:rebuild` no longer runs `StakeholderSeeder`, so it makes no Directory Search calls. Deployments run the seeder for `SUPER_ADMIN_NETIDS`; locally, the seeded NUIT Administrator is a Super Administrator.
- The environment lockdown page is a Filament page in the app panel, and lockdown now applies to app panel routes.
- API users can no longer open the app panel. They authenticate with bearer tokens and never had a way to sign in.
- API request logs record who made each request as `principal_type`, `oauth_client_id`, `token_id` and `grant_type` instead of `access_token_id`, so refused requests and service clients are logged too.
- `ManageApiUsers` is now `ManageApiAccess` (`manage-api-access`). The API area of the administration panel requires it, or `ViewApiRequestLogs` for request logs, instead of `ManageAll`.
- Requires `laravel/passport` `^13.8` and `northwestern-sysdev/chassis` `^1.3`.
- Panels, the public layout and the error layout use the Department Templates 4.0 wordmark and fonts. The app panel and public pages have the Northwestern footer; the administration panel does not.
- The support request confirmation email shows the reference number, subject, submission time and the user's message; the support team's email leads with the request.
- Browser and PHP Sentry reports share one user context, `SentryExceptionHandler::userContext()`.
- Livewire's pagination theme is `tailwind`.
- Requires `northwestern-sysdev/northwestern-filament-theme` `^4.1`.

### Fixed

- Setting `LOGIN_CHALLENGE_RETENTION_DAYS` or `API_REQUEST_LOG_RETENTION_DAYS` to `null` now keeps records, as documented. The settings were cast to an integer, so `null` became `0` and pruning deleted every record.
- Accessibility: callouts directly under a page title no longer skip heading levels, table cells that link to a record show a placeholder instead of an empty link, table placeholders and the platform overview's heatmap labels meet color contrast, the heatmap cells no longer carry labels axe rejects, and the sign-in page's "or" divider meets color contrast.
- Login codes sent by an administrator, or read on another device, can be entered. Every login code email links to the code step for its code; before, only a code requested in the same browser session worked.
- The API rate limit applies per API user, as documented. The limiter ran before the access token was authenticated, so it limited by IP address.
- API clients over the rate limit get a 429 Problem Details response with `Retry-After` instead of a 500.
- `TDX_TICKET_APP_NAME` and `TDX_CLIENT_APP_NAME` take effect. The starter ships `config/team-dynamix.php`, because `tdx-php-sdk`'s own config read them with `config()` instead of `env()`.
- Health check history is pruned after `keep_history_for_days`; nothing pruned it before.
- `/api/health` refuses every request until `HEALTH_SECRET_TOKEN` is set. It was public while the token was empty, which is how `.env.example` ships it.
- `/api/health` no longer returns 503 for checks skipped where they don't apply, such as the database and queue checks outside production. Spatie treats skipped checks as failures by default.
- A Directory Search outage during sign-in shows the 503 page, or a Problem Details 503 on the API, instead of a 500.
- Directory sync no longer resets a user's timezone to `DEFAULT_USER_TIMEZONE` at every sign-in; it sets it only when the user is created.
- Permission checks during API requests use the `web` guard the roles and permissions belong to. With Passport's `api` guard as the default, Spatie looked them up under `api` and found none.

## [v2.6.0] - 2026-10-01

### Breaking

- The administration panel's global search is now opt-in per resource (`globalSearchResourceOptIn()`), so each new resource no longer adds a query to every global search keystroke. Users, Roles, and Support Tickets opt in and return at most 10 results each, and the global search debounce rose from `500ms` to `750ms`. Downstream projects with their own globally searchable resources must add `protected static bool $isGloballySearchable = true;` to keep them in global search.

### Added

- Added two `audits` indexes, built concurrently so the migration does not block audit writes: `created_at` for the Audit Logs default sort, and a partial index for the Role Activity table, its stats widget, and its search. With 2M audit rows, the first page of each table drops from seconds to under 10 ms.

### Changed

- The Audit Logs and API Requests tables use simple pagination, which shows previous and next links instead of a total count and page numbers, and skips the `COUNT(*)` that ran on every request and every API Requests auto-refresh.
- Narrowed table search on the Users, Audit Logs, API Requests, Login Records, and Role Activity tables. Text columns hidden by default (URL, user agent, trace ID, IP address, token name, and route name) are searched individually instead of globally. Columns that a filter already covers, plus timezone, are no longer searchable. The log tables match the search as a single phrase with a `750ms` debounce.
- Consolidated widget and overview queries: the Role Activity stats widget runs one aggregate query instead of five, the Login Records stats widget two instead of four, the API Requests status and top-endpoint charts one fewer each, and the API overview's 24-hour request stats one instead of three. The Platform overview reads the latest health check results once per render.
- The Audit Logs "Record" filter loads its options with a loose index scan over the morph index instead of `SELECT DISTINCT` across every audit row.
- The Role Definition History table loads role types once per request instead of querying for each role type change it displays.

### Fixed

- Fixed a `TypeError` that broke Role Activity table search for any input. `UserSearch::applyToRelation()` now uses `whereHasMorph()` for polymorphic relations.
- Fixed a `TypeError` that broke the Users list page when `API_ENABLED` resolved to a non-boolean string such as `'1'`.

## [v2.5.0] - 2026-09-29

### Breaking

- Renamed the S3 disk's `minio_console` config key to `console_url` and its `AWS_MINIO_CONSOLE` environment variable to `S3_CONSOLE_URL`. `.env.example` sets it to the RustFS console at `http://localhost:9001`. The key no longer falls back to a stale Homestead address, so the administration panel's "RustFS Console" developer tools link (formerly "MinIO Console") only appears when the URL is configured. Downstream projects must rename `AWS_MINIO_CONSOLE` to `S3_CONSOLE_URL` in their `.env` files and deployed environments, and update any references to `filesystems.disks.s3.minio_console`.
- Updated Cypress from `15.20.1` to `16.1.0`, which removes `Cypress.env()`. The `checkAxeViolations` command now reads `axe_skip_failures` and `axe_excluded_selectors` with `Cypress.expose()`, and `cypress.config.js` declares them under `expose` instead of `env`. The `experimentalMemoryManagement` option was removed because Cypress 16 replaces it with `manageBrowserMemory`, which is on by default. `keystrokeDelay` is set back to Cypress 15's `10`ms: Cypress 16 lowered the default to `0`, and the OTP input moves focus to the next box on Alpine's next tick, so typing a code with no delay scrambled it and broke both login-code specs. Node-side values such as `SKIP_DATABASE_REBUILD` still arrive through `--env` and `config.env`. Downstream projects with custom specs or commands that call `Cypress.env()` must move non-sensitive values to `Cypress.expose()` and secrets to `cy.env()`, following the [Cypress 16 migration guide](https://docs.cypress.io/app/references/migration-guide).
- Updated `@sentry/browser` from `10.70.0` to `11.1.0`. Sentry v11 replaces `sendDefaultPii` with a `dataCollection` option whose defaults collect user info, cookies, HTTP headers, and request and response bodies. The `window.Sentry.init` wrapper in `resources/js/bootstrap.js` now passes the v10-equivalent `dataCollection` baseline from Sentry's migration guide, since the layout's `Sentry.init()` options set none; options passed by a caller still take precedence. v11 also streams spans by default, which the Sentry tunnel forwards unchanged. Downstream projects that call other Sentry browser APIs should review the [v10 to v11 migration guide](https://github.com/getsentry/sentry-javascript/blob/develop/MIGRATION.md).

### Changed

- Replaced MinIO with RustFS `1.0.0` for local development. `herd.yml` now provisions Herd's `rustfs` service, and `.env.example` points `AWS_ENDPOINT` at Herd's `https://rustfs.herd.adoes.northwestern.edu` domain, which requires "Serve over HTTPS" in the RustFS service settings. RustFS sends no CORS headers by default, so the installation guide now has developers save the console's default Bucket CORS rule to allow Livewire's direct browser uploads, with an equivalent `rc` CLI alternative. It also covers Herd's Windows differences (the `rustfs-9000.herd` domain and the Internal API Port that collides with the RustFS console on `9001`) and notes that Herd publishes RustFS `1.0.0` only for Apple silicon.
- `AWS_URL` in `.env.example` now includes the bucket name, matching Herd's documented configuration, so `Storage::url()` builds correct path-style URLs.
- Updated PHP dependencies, including Laravel `13.34.0`, Filament `5.9.0`, Livewire `4.4.7`, Telescope `5.25.0`, `northwestern-sysdev/northwestern-laravel-ui` `v4.2.0`, `northwestern-sysdev/laravel-soa` `v12.0.1`, `sentry/sentry-laravel` `4.28.0`, `zircote/swagger-php` `6.11.0`, Pest `5.2.1`, PHPUnit `13.3.4` (the newest release Pest `5.2.1` allows), Larastan `3.12.2`, PHPStan `2.2.16`, Rector `2.6.7`, Pint `1.32.1`, and related transitive packages. Transitive packages moved to new majors where their dependents allow them (`brick/math` `1.0.0`, `phpseclib/phpseclib` `4.0.1`, `guzzlehttp/uri-template` `2.0.1`, `hamcrest/hamcrest-php` `3.0.0`); the application does not use any of them directly. Filament frontend assets were republished, and 20 stale Inter font files left behind by earlier Filament upgrades were removed.
- Updated frontend dependencies, including Vite `8.3.1`, `prettier-plugin-astro` `1.1.0` (rewritten on Astro 7's compiler), Prettier `3.9.9`, `@pierre/diffs` `1.5.1`, Axios `1.20.0`, Sass `1.105.0`, and related tooling. `cypress-axe` `1.7.0`, the latest release, declares Cypress `15` as its maximum and does not call any API that Cypress `16` removed, so `pnpm-workspace.yaml` allows Cypress `16` for it through `peerDependencyRules`.
- Updated the documentation site to Astro `7.3.5`, Starlight `0.42.4`, `@nu-appdev/northwestern-starlight-theme` `1.7.0`, `starlight-links-validator` `0.26.0`, Mermaid `11.17.2`, `starlight-openapi` `0.26.3`, and Sharp `0.35.5`, and raised its Node.js engine to `>= 22.12.0` to match Astro's minimum. Starlight `0.42` removes the `<starlight-menu-button>` wrapper around the mobile menu button, and theme `1.7.0` styles its replacement `.sl-menu-button` so the button keeps its translucent treatment on the purple header. `starlight-links-validator` `0.26` requires Starlight `0.42`. Mermaid `12` is held back until `astro-mermaid` supports it.
- Updated GitHub Actions dependencies: `ctrf-io/github-test-reporter` from `v1.1.0` to `v1.3.0`, `cypress-io/github-action` from `v7.4.2` to `v7.4.5`, and `pnpm/action-setup` from `v6.0.10` to `v6.1.0`. CI now installs pnpm `12`, which reads the existing lockfiles and workspace settings unchanged; the `engines` field still allows pnpm `11`. The deployment guide's example workflow and the commented OpenTofu validation steps now pin actions to full commit SHAs with version comments, matching the repository's own policy, and reference OpenTofu `1.12.6`.
- Updated zizmor from `1.29.0` to `1.30.1`. Its new `self-repository` audit recommends GitHub's `$/` syntax for local actions, which actionlint `1.7.12` rejects, so `.github/zizmor.yml` disables that audit until actionlint supports it.
- Corrected the requirements page, which still listed Node.js `v25.x` and pnpm `^10.0`, to Node.js `v26.x` and pnpm `11.0+`.

### Fixed

- Replaced the MinIO service container in the Cypress job with RustFS `1.0.0`, pinned by digest. MinIO has archived its open-source repository and removed the `minio/minio` and `minio/mc` images from Docker Hub, so `minio/minio:edge-cicd` no longer resolves. The Quay mirror no longer allows anonymous pulls, and `dl.min.io` answers `410` for the `mc` client that created the bucket. The bucket is now created with the AWS CLI already on the runner, which also applies a CORS rule for `http://localhost:8000` so Livewire can upload directly from the browser with signed `PUT` requests. The S3 credentials moved to shared workflow-level `S3_USERNAME` and `S3_PASSWORD` variables, and the unused RustFS console is disabled.
- Updated `phpunit/php-code-coverage` from `14.3.0` to `14.3.5`, with `nikic/php-parser` `5.9.0` as its required dependency, so the PR check workflow's coverage merge works again. `php-code-coverage` `14.3.5` bumped the `.cov` serialization format from version 3 to 4, and the workflow's floating `phpunit/phpcov:^13` tool resolved to `13.1.1`, which requires it and rejects the version 3 shards the locked `14.3.0` wrote.
- Corrected the `LoginTrendsChartWidget` collection key annotations that PHPStan `2.2.16` and Larastan `3.12.2` now check. Hourly statistics are keyed by integer hour, because PHP converts the numeric strings PostgreSQL returns, so the lookup no longer casts the hour to a string; daily statistics are keyed by `Y-m-d` date strings. `TeamDynamixCacheRepositoryTest` now captures each lookup in its own variable, since PHPStan remembers the result of repeated identical calls.

### Security

- Resolved every open Dependabot alert. The documentation site's Astro update patches remote code execution through AVIF image optimization (GHSA-26w7-cxv4-gfx2, critical) and an authorization bypass when stripping the configured base path (GHSA-376h-93r7-7g6f). Transitive updates patch Sharp's bundled libheif (GHSA-rgj7-g3m4-5g8c), SVGO `removeScripts` sanitization bypasses (GHSA-w27v-7q3p-w38r, GHSA-4vpr-x523-8j87), `js-yaml` merge-key CPU exhaustion (GHSA-2883-xcg3-v3hh), `devalue` (GHSA-9rgm-9g3h-6x36) and `undici` (GHSA-3wwx-pv8p-q78v) denial of service, and five `fast-uri` host confusion and request forgery advisories in both the application and documentation lockfiles (GHSA-qw65-cvwx-89v3, GHSA-jqff-g426-hqxp, GHSA-f65p-4m7j-42xc, GHSA-fph4-wmhf-6fwf, GHSA-5jgf-p345-68v8).
- Patched the remaining `pnpm audit` findings in the application lockfile: Cypress `16` brings `qs` `6.16.0` (GHSA-4mjr-xmp4-gh2g), and a `yaml` `>=2.8.3` override in `pnpm-workspace.yaml` fixes stack exhaustion from deeply nested collections in the copy that the Blade Prettier plugin's Tailwind CSS `3` resolves.

## [v2.4.0] - 2026-08-14

### Added

- Added a `typecheck` package script that runs `tsc --noEmit` against the root TypeScript config, and widened that config's `include` to cover `cypress/support/**/*` so the custom Cypress command type augmentations in `cypress/support/index.d.ts` resolve during type-checking.

### Changed

- Updated PHP dependencies, including Laravel `13.25.0`, Filament `5.7.6`, Livewire `4.4.0`, Telescope `5.22.1`, `northwestern-sysdev/chassis` `v1.1.3`, `sentry/sentry-laravel` `4.27.0`, `zircote/swagger-php` `6.5.3`, `spatie/laravel-health` `1.40.2`, Rector `2.6.2`, Pint `1.30.5`, and related transitive packages. Filament frontend assets were republished after the package update.
- Updated Pest from `4.7.4` to `5.1.1` and PHPUnit from `12.5.30` to `13.3.0`. Tests that assert on exception message fragments now use `expectExceptionMessageIsOrContains()` to accommodate PHPUnit 13's stricter `expectExceptionMessage()` semantics, and the PR check workflow's coverage merge tool moved from `phpunit/phpcov:^12` to `^13` to match the new `php-code-coverage` major version.
- Restructured `FindOrUpdateUserFromDirectory::findExistingUser()` to select the user query with an intermediate `match` assignment. `php-code-coverage` `14` marks multi-line `return match (...)` lines as executable, but an upstream PHP line-attribution defect prevents coverage drivers from ever recording hits on them, which falsely reported the line as uncovered.
- Moved `MarkdownWithJiraLinksCast` from the legacy `Foundation` domain into `Core` (`App\Domains\Core\Casts`), relocated its test to match, and removed the now-empty `Foundation` domain directory.
- Updated TypeScript from `6.0.3` to `7.0.2`, adopting the native compiler. Prettier's organize-imports plugin and both Cypress TypeScript configs were verified against the new toolchain.
- Updated Node.js from `25` to `26` and `@types/node` to `26.2.0`, aligned across `.nvmrc`, the package `engines` field, GitHub Actions workflows, the shared setup action, and documentation examples.
- Updated frontend dependencies, including Vite `8.2.1`, Cypress `15.20.1`, `@sentry/browser` `10.70.0`, Tailwind CSS `4.3.3`, `@pierre/diffs` `1.3.5`, `laravel-vite-plugin` `3.2.0`, Axios `1.19.0`, Sass `1.102.0`, Prettier `3.9.6`, `@fortawesome/fontawesome-free` `7.3.1`, and related tooling.
- Updated the documentation site to Astro `7.2.1`, Starlight `0.41.7`, `@nu-appdev/northwestern-starlight-theme` `1.6.0`, Mermaid `11.16.1`, `starlight-links-validator` `0.25.3`, and `starlight-openapi` `0.26.1`.
- Updated GitHub Actions dependencies: `actions/setup-node` from `v6` to `v7`, `actions/cache` from `v5` to `v6`, `lcollins/checkstyle-github-action` from `v3.2.0` to `v4.0.0`, and the commented OpenTofu validation examples to `opentofu/setup-opentofu@v2` and `borchero/terraform-plan-comment@v3`. The shared setup action now defaults to Node.js `26` and pnpm `11.x`, and the deployment guide's example workflow references the current action versions.
- Removed the ADOES Bot personal access token from the PR check workflow; jobs now declare explicit `permissions` blocks and authenticate with the default `GITHUB_TOKEN`, including the scope detection, unit test result publishing, and coverage report jobs.

### Fixed

- Declared `determine-scope` as a dependency of the unit test results publisher job, whose `if:` condition already referenced that job's outputs, and cleaned up legacy backticks, unquoted variables, and an unused loop variable in workflow shell scripts flagged by the first actionlint run.

### Security

- Added a workflow lint job to the PR checks: `actionlint` `1.7.12` validates workflow correctness (expression typing, `needs` wiring, shellcheck on run blocks) and `zizmor` `1.29.0` audits security posture. The zizmor policy in `.github/zizmor.yml` requires every action to be pinned to a full commit SHA, and its two suppressions document the checkouts that must persist credentials to push.
- Patched two high-severity advisories in the documentation site's transitive dependencies: `js-yaml` `4.3.1` (CVE-2026-59870, quadratic CPU consumption in `!!omap` resolution) and `form-data` `4.0.6` (CRLF injection in multipart field names), the latter enforced with a pnpm override until `httpsnippet` raises its own constraint.
- Pinned every third-party GitHub Action across workflows and the shared setup action to a full commit SHA with a version comment, preventing mutable-tag supply chain attacks.
- Hardened the PR check workflows: read-only jobs no longer check out the PR head branch (they now test the merge commit) and disable git credential persistence, and the unit test, docs validation, smoke test, and docs deployment jobs declare explicit token `permissions`. The release-triggered smoke test also disables Node package-manager caching to close its cache-poisoning surface.

## [v2.3.0] - 2026-07-06

### Added

- Added a `registerStandardIntercepts()` Cypress command that aliases the `/livewire/update` route as `@livewireUpdate` and stubs `/broadcasting/auth` as `@broadcastAuthBlocker`, replying `200` to silence the recurring broadcast-auth `403` responses during end-to-end runs.

### Changed

- Updated PHP dependencies, including Laravel `13.18.1`, Filament `5.6.8`, Livewire `4.3.3`, `spatie/laravel-permission` `8.3.0`, `zircote/swagger-php` `6.3.1`, `northwestern-sysdev/chassis` `v1.1.2`, `owen-it/laravel-auditing` `v14.0.6`, `league/flysystem-aws-s3-v3` `3.35.2`, Pest `4.7.4`, PHPUnit `12.5.30`, Rector `2.5.4`, and related transitive packages. Filament frontend assets were republished after the package update.
- Modernized three application classes with the updated Rector ruleset: `FilesystemValidator` now type-hints the closure parameter passed to `array_all`, and the `SupportTicketConfirmation` and `SupportTicketMessage` mailables drop their redundant `@return $this` docblocks now that `build(): static` declares the return type.
- Updated frontend dependencies, including Vite `8.1.3`, `@sentry/browser` `10.63.0`, Cypress `15.18.0`, Tailwind CSS `4.3.2`, `@fortawesome/fontawesome-free` `7.3.0`, Axios `1.18.1`, Prettier `3.9.4`, and related tooling.
- Updated the documentation site to Astro `7.0.6`, Starlight `0.41.3`, `astro-mermaid` `2.1.0`, Mermaid `11.16.0`, Sharp `0.35.3`, `starlight-links-validator` `0.25.2`, and `starlight-openapi` `0.26.0`.
- Enabled Cypress `experimentalMemoryManagement` and capped `numTestsKeptInMemory` at `5` to reduce browser memory pressure across long end-to-end runs, and removed the per-suite `after()` hook that reactivated the local `.env` file and cleared cached config now that Cypress environment swapping is scoped to local runs.

## [v2.2.0] - 2026-06-18

### Added

- Adopted `northwestern-sysdev/chassis` `v1.1.1` database pause detection in the application exception bootstrap. RDS/Aurora Serverless wake-up failures, including Blade-wrapped `ViewException` instances, now render the branded `errors.database-paused` response instead of falling through to the generic 500 page. A PHPUnit feature test covers the wrapped PostgreSQL timeout path.

### Changed

- Updated PHP dependencies, including Laravel `13.16.1`, Filament `5.6.7`, `northwestern-sysdev/chassis` `v1.1.1`, `northwestern-sysdev/northwestern-filament-theme` `v3.0.2`, `sentry/sentry-laravel` `4.26.0`, and related transitive packages. Filament frontend assets were republished after the package update.
- Updated frontend dependencies, including `@pierre/diffs` `1.2.11`, `@sentry/browser` `10.58.0`, Cypress `15.17.0`, Axios `1.18.0`, Tailwind CSS `4.3.1`, Sass `1.101.0`, Prettier `3.8.4`, and related tooling. The root workspace now targets pnpm `11.x` and records explicit build approvals for packages that need install-time scripts.
- Updated the documentation site to Starlight `0.40.0`, Astro `6.4.8`, `astro-mermaid` `2.0.4`, Sharp `0.35.1`, and `starlight-links-validator` `0.24.1`. Installation docs now reference pnpm `latest-11`, and the deployment guide was reformatted with the current docs formatter.
- Updated GitHub Actions workflow maintenance dependencies, including `actions/checkout` from `v6` to `v7` and pnpm setup from `10.x.x` to `11.x.x` across PR checks, documentation deployment, smoke tests, and release automation.
- Changed Cypress environment swapping so `.env` / `.env.cypress` file renames only happen during local runs. CI keeps the provisioned environment in place, which allows Chassis database snapshot commands to restore the intended test database instead of accidentally switching to the local Cypress environment file.

### Removed

- Removed the scheduled Pest shard refresh workflow and the `composer test:update-shards` script now that shard timing maintenance is no longer part of the starter template.

## [v2.1.3] - 2026-06-04

### Changed

- Updated PHP dependencies, including Laravel `13.13.0`, Filament `5.6.6`, Livewire `4.3.1`, Pest `4.7.2`, PHPUnit `12.5.28`, Rector `2.4.5`, and related transitive packages. Filament frontend assets were republished after the package update.
- Updated `spatie/laravel-permission` from `7.4.1` to `8.0.0`.
- Updated frontend dependencies, including Vite `8.0.16`, Tom Select `2.6.1`, Cypress `15.16.0`, `@sentry/browser` `10.55.0`, Tailwind CSS `4.3.0`, Sass `1.100.0`, and related tooling.
- Updated the documentation site to Astro `6.4.2`, Starlight `0.39.2`, Mermaid `11.15.0`, Vite `8.0.16`, and `starlight-openapi` `0.25.3`.
- Updated GitHub Actions dependencies: `pnpm/action-setup` from `v5` to `v6` in the shared setup action and `clearlyip/code-coverage-report-action` from `v6` to `v7`.

## [v2.1.2] - 2026-05-08

### Fixed

- Fixed `<x-select :max-options="null">` rendering invalid JavaScript in the async search load callback. The component now emits a JavaScript `null` value for the `l` query parameter instead of leaving the value blank.
- Removed the invalid `combobox` role and `aria-expanded` attribute from the native `<select>` element rendered by `x-select`. Native select elements already expose the correct semantics, and the ARIA combobox role is only valid on supported input/button patterns.
- Aligned `UserFactory::configure()` with Laravel's factory return contract for updated static analysis after dependency upgrades.

### Changed

- Updated PHP dependencies, including Laravel `13.8.0`, Filament `5.6.2`, Livewire `4.3.0`, Pest `4.7.0`, PHPUnit `12.5.24`, Rector `2.4.2`, and related transitive packages. Filament frontend assets were republished after the package update.
- Updated frontend dependencies, including Vite `8.0.11`, Tom Select `2.6.0`, Cypress `15.14.2`, `@sentry/browser` `10.52.0`, Tailwind CSS `4.2.4`, TypeScript `6.0.3`, Sass `1.99.0`, and related tooling.
- Updated the documentation site to Astro `6.3.1`, Starlight `0.39.1`, Vite `8.0.11`, `starlight-links-validator` `0.24.0`, and `starlight-openapi` `0.25.0`. The Starlight sidebar config now wraps autogenerated sections in labeled groups for the `0.39` schema.
- Updated GitHub Actions dependencies: `pnpm/action-setup` from `v5` to `v6` and `peter-evans/create-pull-request` from `v7` to `v8`.

## [v2.1.1] - 2026-05-05

### Changed

- Updated Rector's PHPUnit configuration to use composer-aware PHPUnit rules and the PHPUnit code-quality prepared set, then refreshed affected tests with the resulting provider and assertion modernizations.
- Changed the Composer `format:php` script to run Pint with `--parallel`.

## [v2.1.0] - 2026-04-27

### Changed

- Adopted [`northwestern-sysdev/chassis`](https://github.com/NIT-Administrative-Systems/chassis) `v1.0.0` for shared Laravel infrastructure that now lives outside the starter, including audited model helpers, idempotent seeders, snapshot and rebuild commands, API problem details, config validation, and related support code.

## [v2.0.0] - 2026-04-21

### Breaking

- Upgraded `laravel/framework` from `^12.0` to `^13.0`. Downstream projects will need to follow the [Laravel 13 upgrade guide](https://laravel.com/docs/13.x/upgrade) for any project-level customizations.
- Replaced the `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` middleware with its Laravel 13 successor `Illuminate\Foundation\Http\Middleware\PreventRequestForgery` in `routes/auth.php` (Azure AD OAuth callback and Sentry tunnel) and `ImpersonationControllerTest`. Downstream projects with `withoutMiddleware()` calls, custom `except` lists, or other references to `VerifyCsrfToken` must update to `PreventRequestForgery`.
- Updated default cache, Redis, and session prefixes in `config/cache.php`, `config/database.php`, and `config/session.php` to the Laravel 13 format (hyphen-separated with `Str::slug()`, snake-cased session cookie). Cache, Redis, and session keys from pre-upgrade deployments will not be found under the new prefixes — expect a one-time cache miss and logged-out sessions on deploy, or override the `CACHE_PREFIX`, `REDIS_PREFIX`, and `SESSION_COOKIE` env vars to preserve the old values.
- Bumped `northwestern-sysdev/laravel-soa` from `^11.2` to `^12.0`. The `WebSSOAuthentication` trait now regenerates the session on login and invalidates it on logout to prevent session fixation. See the [laravel-soa v12 changelog](https://github.com/NIT-Administrative-Systems/SysDev-laravel-soa/blob/main/CHANGELOG.md) for details.

### Added

- Stored generated `users.full_name` column plus a `pg_trgm` GIN trigram index (`2026_04_21_120000_add_trigram_index_to_users_full_name.php`, PostgreSQL only). `UserBuilder::searchByName` takes the indexed fast path for comma-less terms; on a 50k-row synthetic dataset an unanchored `ILIKE '%term%'` drops from a ~54ms sequential scan to a ~1ms bitmap-index scan. Non-PostgreSQL drivers continue to use the portable `CONCAT_WS` fallback, so downstream MySQL projects are unaffected.
- Redesigned the `Overview` admin page (`/administration/overview`) with operational widgets: a slim health-status ribbon, a queue alert card that surfaces failed-job counts alongside the latest exception excerpt and a top-three breakdown of pending job classes, a Login Activity 7×24 heatmap with per-cell tooltips, an API Traffic sparkline with inline p95 latency (gated on `config('api.enabled')`), a consolidated Feature Flags section, and a Scheduled Tasks table sourced from `php artisan schedule:list --json`. The Environment / Services / Storage / Error Tracking blocks are now grouped inside a single `Configuration` card with description-list layouts instead of four stacked sections, and Lockdown Mode moved from Environment to Feature Flags alongside the other runtime toggles.

### Changed

- Bumped `mews/purifier` from `^3.4` to `^3.4.4` for Laravel 13 support.
- Bumped `laracasts/cypress` to `3.0.4` to pick up the Laravel 13 `illuminate/support` constraint.
- Annotated `AutomaticallyOrderedScope` with `@implements Scope<Model>` to satisfy the now-generic `Illuminate\Database\Eloquent\Scope` interface in Laravel 13. Downstream custom scopes implementing `Scope` will need the same annotation to stay PHPStan-clean.
- Updated Laravel version references in documentation from `12.x` to `13.x`.
- Aligned the `User::fullName` accessor with the new generated-column expression (`trim(($first_name ?? '').' '.($last_name ?? ''))`) so PHP and SQL produce byte-identical values regardless of read path or database driver. The accessor prefers the stored column when it's loaded on the model and falls back to the computed value for unpersisted instances; `full_name` was dropped from `$appends` now that it's a real column.

### Fixed

- Stored cross-site scripting in the impersonation global-alert sink. An administrator with the `CreateUsers` permission could plant HTML or JavaScript in a user's `first_name`/`last_name`; when a separate administrator with `ManageImpersonation` later impersonated that user, the payload executed in the impersonator's session through the `{!! $activeAlert['message'] !!}` sink in `northwestern-sysdev/northwestern-laravel-ui`. `auth()->user()->full_name` is now wrapped with `e()` before being interpolated into the alert's heredoc.
- `Overview::getQueueStatus` no longer silently reports zero pending jobs on non-database queue drivers. The earlier `config('queue.default') === 'database'` guard meant Redis-backed projects (the starter default) saw no pending-job count in the queue alert even when work was stuck.

## [v1.17.0] - 2026-04-17

### Changed

- Bumped `northwestern-sysdev/northwestern-filament-theme` from v2.4.1 to v2.5.0.
- Extracted `TracksBroadcastDateRange` trait from the `UserLoginRecords` widgets (`LoginRecordsStatsWidget`, `LoginTrendsChartWidget`, `LoginsBySegmentChartWidget`), mirroring the `BaseApiRequestChartWidget` pattern already used on the API side. Dropped ~45 lines of byte-identical mount/listener/property code.
- Consolidated the two identical filter-widget Blade views (API request logs, user login records) into a single shared `resources/views/filament/support/widgets/filter-widget.blade.php`.
- Shared PHPStan docblock shapes between audit writers and readers: `Audit::getChangedRoles` now imports `RoleData` from `AuditsRoles`, `RoleDefinitionHistoryTable` imports `PermissionData` from `AuditsPermissions`, and `Platform\Overview` uses a class-level `InfoRow` type instead of repeating the shape inline.
- Stripped ~30 comments that only restated the following call site across console commands, middleware, controllers, audit concerns, and Filament tables. Tightened narrative docblocks in `SchemaChecksumManager` and `IdempotentSeederResolver::topologicalSort` to concise summaries.

### Breaking

- Moved `ApiRouteInspector` from `App\Domains\Core\Services` to `App\Domains\Auth\Services` to match its actual responsibility (inspecting routes for the `AuthenticatesAccessTokens` middleware). This breaks the `App\Domains\Core ↔ App\Domains\Auth` circular dependency. Downstream projects referencing the old FQCN must update the `use` statement.

## [v1.16.0] - 2026-04-17

### Changed

- Refactored Filament admin resources and relation managers to reuse shared table builders and named routes instead of depending directly on resource classes. API request logs, user login records, role activity, audits, role assignment flows, and user creation redirects now share the same configuration paths in both resource pages and relation-manager tabs.
- Simplified Filament search, filter, and badge rendering code by reusing shared helpers. Role activity and role definition history now use the common `DateRangeFilter`, `BadgePillRenderer`, `UserSearch`, and `UserOptionLabel` services rather than duplicating query, date-range, badge markup, and user-label logic inline.
- Tightened Cypress support typings and command implementations. The plugin and custom commands now use explicit JSON, route, and Artisan parameter types, the custom `visit()` overwrite is typed for route targets, selector helpers accept typed get options, and Axe excluded selectors are parsed correctly as comma-delimited values.
- Tightened several PHP and TypeScript type declarations in audit, auth, config validation, console, and EventHub helper code, including safer Livewire snapshot decoding in `Auditable` and narrower JSON value typing for the audit diff viewer.
- Added automated Pest shard maintenance for CI. Composer now exposes `test:update-shards`, the repository now tracks `tests/.pest/shards.json`, and a scheduled GitHub Actions workflow refreshes shard timings weekly and opens a PR when they drift.

## [v1.15.2] - 2026-04-15

### Changed

- Reworked health check scheduling and registration for scale-to-zero RDS environments.
    - Gated `DispatchQueueCheckJobsCommand` and `RunHealthChecksCommand` behind `App::isProduction()` in `routes/console.php`. Non-prod no longer keeps the database awake with per-minute health traffic; run `php artisan health:check` on demand in local/dev.
    - Dropped the queue heartbeat from `everyMinute()` to `everyFiveMinutes()` in prod. Raised the `QueueCheck` staleness threshold from 5 to 15 min (`failWhenHealthJobTakesLongerThanMinutes(15)`) to match the new cadence without flapping at the boundary.
    - Rewrote `HealthServiceProvider` to register `DatabaseCheck`, `RedisCheck`, and `QueueCheck` with fluent `->if(...)` gates inside a single `Health::checks([...])` array, replacing the imperative `if ($condition) { $checks[] = ... }` appends. `DatabaseCheck` and `QueueCheck` gate on `App::isProduction()`; `RedisCheck` gates on `usesRedisDriver()`.
    - Scheduled `ScheduleCheckHeartbeatCommand` every minute in all environments. It writes a single cache entry (no DB or queue traffic) so it's safe in non-prod, and without it Spatie's `ScheduleCheck` reports "the schedule did not run yet" indefinitely.
- Dropped `EnsureApiEnabled` from the `/api/health` route in `routes/api.php`. `RequiresSecretToken` already gates the endpoint, which feeds uptime monitors and AWS target-group health checks, so it needs to stay reachable even when the rest of the API is disabled via `config('api.enabled')`.

## [v1.15.1] - 2026-04-09

### Fixed

- Added `captureException` to the selective Sentry JS imports and `window.Sentry` object. The v1.15.0 migration to named imports omitted it, breaking `Sentry.captureException()` calls from inline scripts and the browser console.
- Registered `setSentryUserContext` in `AppServiceProvider` so the `northwestern-laravel-ui` Blade template calls `Sentry.setUser()` on every page load. Without this, JS errors reported from the browser had no user context attached.
- Pinned `phpunit/phpcov` to `^12` in the CI coverage workflow. Unpinned installs pulled an incompatible version, failing the coverage merge step.
- Added `AWS_SSL_VERIFY` option to the S3 filesystem config so MinIO and other S3-compatible stores with self-signed certificates work in local development.

### Changed

- Database snapshot `create` and `restore` commands now require an explicit filename argument. The implicit `database-dump` default was removed. `delete` and `info` remain optional with interactive selection. The Cypress plugin creates snapshots as `cypress`, and `loadDatabaseSnapshot()` defaults to that name.

## [v1.15.0] - 2026-04-01

### Added

- `@sentry/vite-plugin` in `vite.config.js` for source map uploads, release creation, and commit association ("Suspect Commits"). Disabled when `SENTRY_AUTH_TOKEN` is absent. Deletes `.map` files after upload.
- Sentry documentation page (`docs/src/content/docs/features/sentry.mdx`) covering JS initialization, selective imports, adding SDK features, PHP configuration, and source map setup for deploy workflows.

### Changed

- Sentry JS imports in `resources/js/bootstrap.js` changed from `import * as Sentry` to four named imports (`browserTracingIntegration`, `captureFeedback`, `init`, `setUser`). The namespace import bundled the entire SDK, including Replay and Feedback integrations no application uses. `app.js` dropped from 859 KB to 562 KB minified (34%).
- `config/sentry.php` release identifier changed from `env('SENTRY_RELEASE')` to `env('VAPOR_COMMIT_HASH')`. Vapor sets this env var when deploying with the `--commit` flag, already present in deploy workflows.
- Shiki Vite plugin (`resources/js/shiki/vite-plugin.mjs`) refactored from a plain `resolveId()` function to Rolldown's `resolveId.filter` + `resolveId.handler` object form. The filter regex skips the hook for non-matching module IDs, avoiding a function call on every resolve.
- TypeScript upgraded from ^5.9.3 to ^6.0.2. Root `tsconfig.json`: `moduleResolution` changed from `Node` to `Bundler`, removed `allowSyntheticDefaultImports` and `esModuleInterop` (defaults changed in TS6). Cypress `tsconfig.json`: `target` and `lib` changed from `es5` to `ES2015`, added `ignoreDeprecations: "6.0"` for webpack preprocessor compatibility.
- Pinned `livewire/livewire` from `^4.0` to `4.2.2` to work around a regression in later 4.x releases.
- Updated `laravel/tinker` from ^2.10.1 to ^3.0, `cypress-ctrf-json-reporter` from ^0.0.13 to ^0.0.14.

## [v1.14.0] - 2026-03-31

### Added

- Smoke test workflow runs the full test suite (`php artisan test --parallel`) and verifies the Vite manifest before booting the application.
- Smoke test `workflow_dispatch` accepts any branch. Untagged runs resolve to `dev-<branch-name>`.
- Feature flag baseline in `phpunit.xml`: `API_ENABLED`, `LOCAL_AUTH_ENABLED`, `SUPPORT_ENABLED`, and `CHANGELOG_ENABLED` set to `true`. Fresh `composer create-project` installs pass the test suite without `.env` adjustments.

### Fixed

- Smoke test step ordering: `config:cache` ran before the test suite, so `phpunit.xml` env overrides (`DB_CONNECTION=phpunit`) had no effect. Moved tests before config/route/event caching.
- Local auth and support controller tests (`ShowLoginCodeRequestControllerTest`, `ShowLoginCodeFormControllerTest`, `SendLoginCodeControllerTest`, `VerifyLoginCodeControllerTest`, `ContactControllerTest`) and `ApiTestCase` check `Route::has()` or `config()` in `setUp()` and call `markTestSkipped()` when the required routes are absent. Disabling `LOCAL_AUTH_ENABLED` or `SUPPORT_ENABLED` skips these tests instead of failing them.

## [v1.13.3] - 2026-03-31

### Changed

- Upgraded docs site to Astro 6: `astro` ^5.18.1 to ^6.1.1, `@astrojs/starlight` ^0.37.7 to ^0.38.2, `astro-mermaid` ^1.4.0 to ^2.0.1, `starlight-links-validator` ^0.19.2 to ^0.21.0, `starlight-openapi` ^0.22.1 to ^0.24.0.
- Updated `@nu-appdev/northwestern-starlight-theme` from ^1.3.0 to ^1.3.2.
- Added `minimumReleaseAgeExclude` for `@nu-appdev/*` packages in `docs/pnpm-workspace.yaml`, exempting internal packages from the 72-hour minimum release age constraint.
- Updated GitHub Actions: `pnpm/action-setup` v4 to v5, `dorny/paths-filter` v3 to v4, `marocchino/sticky-pull-request-comment` v2 to v3, `lcollins/checkstyle-github-action` v3.1.0 to v3.2.0.
- Updated Composer dependencies: `filament/filament` 5.4.1 to 5.4.3, `fruitcake/laravel-debugbar` 4.1.3 to 4.2.1, `laravel/telescope` 5.18.0 to 5.19.0, `livewire/livewire` 4.2.1 to 4.2.3, `owen-it/laravel-auditing` 14.0.2 to 14.0.3, `sentry/sentry-laravel` 4.23.0 to 4.24.0, `brianium/paratest` 7.19.2 to 7.20.0.
- Updated npm dependencies: `@pierre/diffs` ^1.0.11 to ^1.1.7, `@sentry/browser` ^10.40.0 to ^10.46.0, `cypress` ^15.10.0 to ^15.13.0, `axios` ^1.13.5 to ^1.14.0, `sass` ^1.97.3 to ^1.98.0, `vite` ^8.0.0 to ^8.0.3, and other minor dev dependency bumps.

## [v1.13.2] - 2026-03-31

### Added

- `CHANGELOG.md` with backfilled release history.

### Fixed

- The `pnpm-workspace.yaml` files incorrectly nested the `minimumReleaseAge` under a `settings` key. This has been moved to the root level.

## [v1.13.1] - 2026-03-31

### Added

- `pnpm-workspace.yaml` files in both the project root and `docs/` directory with a `minimumReleaseAge` setting of 4320 minutes (72 hours). pnpm will only install npm package versions published for at least 3 days, reducing exposure to compromised or typosquatted packages in their first hours of publication.

## [v1.13.0] - 2026-03-26

### Added

- `WebSSOController::logout()` method that invalidates the Laravel session (`Auth::logout()`, `Session::invalidate()`, `Session::regenerateToken()`) before delegating to the SSO strategy's logout. The trait's default logout did not clear server-side session state.
- `PropertyTable` and `Property` components from `@nu-appdev/northwestern-starlight-theme/components` are now used throughout the documentation site for all parameter/config tables, replacing raw HTML `<table>` elements. Affected docs include: component-library, changelog, authentication, authorization, API, support-tickets, commands, directory-search, EventHub, and WebSSO reference pages.
- Documentation for `db:seed:list` command options (`--show-dependencies`, `--mermaid`, `--json`) and `db:snapshot:restore --force` flag.
- Documentation corrections for `db:snapshot` commands: argument names changed from `{name}` to `{filename?}` and `db:rebuild` steps updated to reflect the actual implementation (cache clearing, `migrate:fresh`, `DemoSeeder` invocation, IDE helper regeneration).

### Changed

- Upgraded docs theme from custom CSS/components to `@nu-appdev/northwestern-starlight-theme` v1.3.0. This replaced the custom `Hero.astro` component, `ConditionalEditLink.astro` component, `custom.css` (Northwestern purple branding, navigation styling), and `layout.css` with the shared theme plugin configured via `northwesternTheme({ homepage: { showTitle: false, imageWidth: "750px" } })` in `astro.config.mjs`.
- Removed the custom `favicon.ico` from the docs site (now provided by the theme).
- Removed `astro-mermaid` integration config from `astro.config.mjs` (the package is still a dependency but Mermaid setup is now handled by the theme).
- Updated docs dependencies: `@astrojs/starlight` to ^0.37.7, `astro` to ^5.18.1, `astro-mermaid` to ^1.4.0, `mermaid` to ^11.13.0, `starlight-openapi` to ^0.22.1.
- Lowered docs Node.js engine requirement from >= 25.0.0 to >= 22.0.0.
- `StakeholderSeeder::createAndAssignRole()` now checks for already-assigned roles before calling `assignRoleWithAudit()`, filtering out roles the user already has via `$roles->reject(fn (Role $role) => $user->hasRole($role))`. Prevents duplicate audit entries and redundant role assignments during re-seeding.

## [v1.12.0] - 2026-03-23

### Changed

- Bumped minimum PHP version from ^8.4 to ^8.5 across `composer.json`, CI workflows (`.github/actions/common-setup/action.yml`, `check-pr.yml`, `smoke-test.yml`), Herd configuration (`herd.yml`), and README badge.
- Upgraded Vite from ^7.0.0 to ^8.0.0 and `laravel-vite-plugin` from ^2.1.0 to ^3.0.0.
- Updated `@tailwindcss/vite` to ^4.2.2 and `tailwindcss` to ^4.2.2.
- Upgraded `northwestern-sysdev/tdx-php-sdk` from ^0.5.1 to ^0.6.
- Updated Rector configuration from PHP 8.4 to PHP 8.5 rule sets (`SetList::PHP_85`), replacing `DeprecatedAnnotationToDeprecatedAttributeRector` with PHP 8.5-specific skip rules: `NestedFuncCallsToPipeOperatorRector`, `SequentialAssignmentsToPipeOperatorRector`, `AddOverrideAttributeToOverriddenMethodsRector`, and `AddOverrideAttributeToOverriddenPropertiesRector`.
- Updated `config/database.php` MySQL SSL config from deprecated `PDO::MYSQL_ATTR_SSL_CA` to `Pdo\Mysql::ATTR_SSL_CA` (PHP 8.5 namespaced PDO driver constant).
- Streamlined README prose: shortened descriptions for features, overview, and acknowledgements sections without removing content.
- Rebuilt Filament frontend assets (`app.css`, `app.js`, various component JS files).

## [v1.11.0] - 2026-03-20

### Added

- **Role Activity page** (`RoleActivityResource`) accessible from the Roles list via a "Role Activity" header action button. Displays a filterable, searchable table of all `role_assigned` and `role_removed` audit events across the system. The table shows the event type (with color-coded badges), affected user, changed role(s) rendered as Filament-styled pill badges with links, modification origin (UI Action, SSO Provisioning, NetID Event, Role Deleted, or System), performing user (with impersonation indicator), and timestamp. Includes filters for event type, role, user, performer, origin, and date range. Located at `/admin/roles/activity`.
- **Role Activity stats widget** (`RoleActivityStatsWidget`) displayed above the Role Activity table showing total assignments, total removals, counts from the last 7 days, and time since last activity.
- **Role Activity CSV export** (`RoleActivityExporter`) with CSV formula sanitization, exporting event, user NetID/name, role name/type, origin, performer, impersonator, and date columns.
- **Role Activity relation manager** (`RoleActivityRelationManager`) on the User resource's view page, showing a "Role History" tab with per-user role assignment/removal history. Only visible for non-API users when the viewer has the `ViewAuditLogs` permission.
- **Role Definition History page** (`RoleDefinitionHistory`) as a new sub-navigation tab on individual role view/edit pages. Displays a timeline of all audit events for a specific role (`created`, `updated`, `deleted`, `restored`, `permissions_modified`) with an expandable collapsible panel showing a full JSON diff viewer for each entry. The table summarizes attribute changes inline (e.g., name changes shown as old -> new with color-coded badges, permission additions/removals shown as pill groups). Located at `/admin/roles/{record}/history`.
- Sub-navigation on the Role resource configured with `SubNavigationPosition::Top`, showing "Details" and "Definition History" tabs on role view/edit pages.
- `AuditsSeederChanges` trait (`App\Domains\Core\Seeders\Concerns\AuditsSeederChanges`) enables audit logging in seeders by registering the `AuditableObserver` on specified model classes, bypassing the global `audit.console` config. Skips observer registration in testing/CI environments.
- `RoleSeeder` and `PermissionSeeder` now use the `AuditsSeederChanges` trait to produce audit entries for role/permission creation and updates during deployment seeding. `RoleSeeder` also switched from `syncPermissions()` to `syncPermissionsWithAudit()`.
- `StakeholderSeeder` switched from `$user->roles()->sync()` to `$user->assignRoleWithAudit()` with `RoleModificationOrigin::System`, producing audit trail entries for stakeholder role assignments.
- `Audit` model gained an `auditable()` MorphTo relationship (with `withTrashed()`), a `roleActivity` query scope filtering to `role_assigned`/`role_removed` events for User models, and a `getChangedRoles()` method that diffs `old_values`/`new_values` to extract the specific roles that were added or removed.
- `RoleModificationOrigin` enum now implements Filament's `HasColor`, `HasIcon`, and `HasLabel` contracts, providing labels (e.g., "SSO Provisioning", "NetID Event"), colors, and Heroicons for each origin type for display in tables and badges.
- Reusable `diff-toolbar` Blade component (`resources/views/components/diff-toolbar.blade.php`) with split/unified layout toggle and wrap/scroll text overflow toggle, used in both the audit diff viewer and the new role definition history collapsible panels.
- Documentation for the `AuditsSeederChanges` trait in the audit logging docs, explaining usage, which seeders use it, and the distinction between custom audit events and standard Eloquent events.

### Changed

- All export toolbar actions across the application (Roles, Users, Audits, API Request Logs, Support Tickets, User Login Records) now display a consistent gray-colored download icon (`Heroicon::OutlinedArrowDownTray`) instead of appearing as unstyled buttons.

## [v1.10.0] - 2026-03-17

### Added

- Impersonation banner is now provided by the `northwestern-filament-theme` package (v2.1.0) via `NorthwesternTheme::make()->impersonationBanner()`, replacing the custom `impersonation-banner.blade.php` view and `FilamentView::registerRenderHook()` call in `FilamentServiceProvider`.

### Changed

- Removed the `pxlrbt/filament-environment-indicator` package dependency. The `northwestern-filament-theme` package (upgraded from v2.0.0 to v2.1.0) now handles environment indicators, removing the separate `EnvironmentIndicatorPlugin` configuration in `AdministrationPanelProvider`.
- Roles table "Assignment Locked" column now shows a gray `LockOpen` icon with an "Assignment is open" tooltip for unlocked roles, instead of showing no icon and no tooltip. Locked roles continue to show the yellow `LockClosed` icon.

### Fixed

- Default profile photo SVG (`public/images/default-profile-photo.svg`) viewBox cropped from `0 0 700 700` to `120 50 460 460` to remove excess whitespace around the avatar graphic. Now renders at the correct visual size in Filament user menus and avatars.
- WCAG color contrast fix in the role form's "Sensitive Permissions" warning box. Text color classes changed from `text-red-400` (insufficient contrast in light mode) to `text-red-700 dark:text-red-400`, meeting contrast requirements in both light and dark modes.

## [v1.9.2] - 2026-03-16

### Changed

- Extracted the Northwestern Filament theme CSS from inline `public/css/northwestern-sysdev/northwestern-filament-theme/` files (9 CSS modules: variables, buttons, components, forms, layout, navigation, tables, typography, utilities) into the external `northwestern-sysdev/northwestern-filament-theme` package, upgraded from v1.x to v2.0.0
- Theme CSS is now imported via `resources/css/filament/administration/theme.css` from the vendor directory (`dist/theme.css` and `dist/tailwind-tokens.css`) instead of being maintained in the project repository
- `AdministrationPanelProvider` now calls `NorthwesternTheme::make()->withoutAssetRegistration()` since the theme CSS is loaded through the Tailwind build pipeline rather than Filament's asset system
- Updated UI preview screenshots in `art/` directory with the latest Filament theme appearance

## [v1.9.1] - 2026-03-16

### Added

- User avatars in the Filament admin panel via Wildcard photos. The `User` model now implements `HasAvatar` and provides `getFilamentAvatarUrl()`, which returns the user's Wildcard photo URL when `platform.wildcard_photo_sync` is enabled

### Fixed

- CSS refinements to the Northwestern Filament theme: `nu-components.css` tweaks, expanded `nu-layout.css` styles, adjusted `nu-tables.css`, updated `nu-typography.css` and `nu-utilities.css`, and corrected a `nu-variables.css` value

## [v1.9.0] - 2026-03-16

### Added

- Northwestern Filament theme: a custom CSS theme for the Filament admin panel, delivered as 9 modular CSS files (nu-variables, nu-buttons, nu-components, nu-forms, nu-layout, nu-navigation, nu-tables, nu-typography, nu-utilities) implementing Northwestern brand design tokens (purple palette, typography, spacing) and styling for all Filament components
- `northwestern-sysdev/northwestern-filament-theme` v1.0 Composer dependency, providing the `NorthwesternTheme` Filament plugin
- Auto-discovery for configuration validators: new `#[StarterValidator]` PHP attribute and `ConfigValidatorResolver` service that scans `app/Domains/**/Services/ConfigValidation` directories for classes implementing `ConfigValidator`, removing the need to register validators in the command
- `ConfigValidator` interface now includes a `shouldRun()` method, allowing validators to declare themselves as not applicable (e.g., for optional integrations that are not configured); skipped validators are displayed with a distinct "Skipped (not applicable)" indicator in the output
- `ResolvedValidator` value object to carry the validator instance alongside its `#[StarterValidator]` description
- `ValidateConfigurationCommand` logs warnings when a validator throws an exception, including the validator class, description, and exception details
- `RedisCheck` added to `HealthServiceProvider`. Registers when any of the cache, queue, session, or Redis client drivers use Redis
- Login page (`login-selection.blade.php`) renders SSO and local auth cards based on configuration, and shows a "No Sign-in Methods Available" fallback linking to the authentication documentation when neither provider is configured
- Auth routes are now conditionally registered based on feature configuration: local auth routes (`login-code.*`) only register when `local-auth.enabled` is true, Entra ID routes only when `AZURE_CLIENT_ID` and `AZURE_CLIENT_SECRET` are set, and WebSSO routes only when the agentless WebSSO API key or `forgerock-direct` strategy is configured

### Changed

- Enum naming convention overhaul: dropped the `Enum` suffix and switched from `SCREAMING_SNAKE_CASE` to `PascalCase` for enum cases across the entire codebase. Key renames include: `PermissionEnum` to `SystemPermission`, `AccessTokenStatusEnum` to `AccessTokenStatus`, `AuthTypeEnum` to `AuthType`, `AffiliationEnum` to `Affiliation`, `PermissionScopeEnum` to `PermissionScope`, `RoleModificationOriginEnum` to `RoleModificationOrigin`, `TokenExpirationEnum` to `TokenExpiration`, `UserSegmentEnum` to `UserSegment`, `NetIdUpdateActionEnum` to `NetIdUpdateAction`, `ApiRequestFailureEnum` to `ApiRequestFailure`, `ExternalServiceEnum` to `ExternalService`, `TicketSystemEnum` to `TicketSystem`, `SystemRoleEnum` to `SystemRole`
- Exception naming convention overhaul: added the `Exception` suffix. `NoRollback` to `NoRollbackException`, `MissingRequestIpForRestrictedToken` to `MissingRequestIpForRestrictedTokenException`, `TdxLookupFailed` to `TdxLookupFailedException`
- Mailable renamed from `LoginCodeNotification` to `LoginCodeMail` to match Laravel conventions
- Value object `CreationResult` moved from `App\Domains\Support\Gateway` to `App\Domains\Support\Gateways` namespace
- `DirectorySearchCheck` health check: renamed variables (`$result` to `$healthResult`, `$info` to `$directoryLookup`)
- `LoginSelectionController` and `LogoutSelectionController` now detect whether Entra ID credentials are configured (both `client_id` and `client_secret` must be present) before redirecting to OAuth, and fall back when no SSO provider is configured
- `ValidateConfigurationCommand` summary reports passed, failed, and skipped counts separately
- Migration stubs updated with modernized `return new class extends Migration` formatting
- `ConfigValidator` interface: replaced `name(): string` with `shouldRun(): bool`

### Fixed

- `Http::preventStrayRequests()` now scoped to `ci` and `testing` environments instead of running globally, which broke HTTP calls in local development and production
- `LogoutSelectionController` now handles logout when no SSO provider is configured, falling back to local session invalidation instead of attempting an SSO logout redirect that would fail

## [v1.8.0] - 2026-03-09

### Added

- Assignment-locked roles: a new `assignment_locked` boolean column on the `roles` table (via migration `2026_03_02_000000_add_assignment_locked_to_roles_table`) that prevents role assignment/removal through the Filament admin panel. The `Northwestern User` system role is seeded with `assignment_locked: true` by default
- `Role::isAssignmentLocked()` method and `Role::assignable()` query scope for filtering to roles that can be modified through the UI
- `RolePolicy` now includes `attachUser` and `detachUser` authorization methods that check `PermissionEnum::ASSIGN_ROLES` and deny access for system-managed roles. The `update` and `delete` methods now receive the `Role` model and deny modification of system-managed types
- `ForceDetachRoleCommand` (`php artisan role:force-detach`): an emergency Artisan command to remove any role from a user (including assignment-locked roles), with interactive user/role search prompts, mandatory audit trail reason, and `--force` flag for non-interactive environments
- `RoleFactory::assignmentLocked()` state method for testing
- Assignment locked indicator in the Roles table: a lock icon column (`IconColumn`) with warning color and tooltip explaining the role is assigned programmatically
- Warning banner on the View Role page for assignment-locked roles, displaying "This role's assignment is managed programmatically and cannot be changed through the admin panel"
- `assignment_locked` property exposed in the API schema for the Role resource
- CI workflow: added a "Fail on Errors" step in `check-pr.yml` that fails the "Unit Test Results" job when test shards report failures. Fixes a bug where `publish-unit-test-result-action` could attach to unrelated workflows and bypass branch protection rulesets

### Changed

- Removed `Role::canBeManaged()` method (which performed inline permission checks) in favor of policy-based authorization via `RolePolicy::attachUser()` and `RolePolicy::detachUser()`
- Roles table edit action now uses `Gate::allows('update', $record)` instead of inline permission checks
- `UsersRelationManager` on roles and `RolesRelationManager` on users updated to respect the new policy methods
- System Managed role warning section label shortened from "System Managed Role" to "System Managed" in the View Role page

### Fixed

- `Http::preventStrayRequests()` scoped to CI and testing environments only. Was blocking real HTTP requests in development and production
- `ApiRequestLogsTable` "View User" action now handles unauthenticated API request logs by checking for `user_id` before generating the URL, and shows "Unauthenticated" placeholder text for the username column when no user is associated

## [v1.7.2] - 2026-03-03

### Added

- `SSOValidator`: a new configuration validator that detects the active SSO authentication strategy (Microsoft Entra ID or Online Passport/agentless WebSSO) and validates the appropriate credentials. When Online Passport is detected (via `WEBSSO_API_KEY` or `forgerock-direct` strategy), it checks for `WEBSSO_API_KEY` and `WEBSSO_API_URL_BASE`; otherwise it validates `AZURE_CLIENT_ID` and `AZURE_CLIENT_SECRET`
- `DirectorySearchValidator`: checks whether `DIRECTORY_SEARCH_API_KEY` is configured, replacing the check bundled inside `EnvironmentVariablesValidator`
- `EventHubValidator` now supports a "skipped" state: when all EventHub environment variables are blank, it reports as passed with a "not configured (optional)" message instead of failing, recognizing EventHub as an optional integration. Partial configuration (some variables set, others missing) still reports as a failure with a new hint suggesting removal of all `EVENT_HUB_*` variables if EventHub is not needed

### Changed

- Replaced `EnvironmentVariablesValidator` (which checked `AZURE_CLIENT_SECRET` and `DIRECTORY_SEARCH_API_KEY` together) with the more granular `SSOValidator` and `DirectorySearchValidator`
- `ValidateConfigurationCommand` now registers `SSOValidator` and `DirectorySearchValidator` in place of the removed `EnvironmentVariablesValidator`
- Reorganized `.env.example` into labeled sections (Authentication, API, Northwestern Integrations, Features, Third-Party Services, Testing) with descriptive separator comments, and regrouped rate-limiting variables to sit alongside their related features

## [v1.7.1] - 2026-03-02

### Added

- Smoke test CI workflow (`.github/workflows/smoke-test.yml`): an end-to-end test that runs on every release (or on-demand via `workflow_dispatch`). Performs `composer create-project`, validates post-install hooks (`.env` creation, `APP_KEY` generation, `.starter-version.yaml` version match), runs migrations and seeders, builds frontend assets with pnpm, and boots the application server to verify a successful HTTP response
- "Applying Upstream Updates" documentation guide (`docs/src/content/docs/guides/applying-upstream-updates.mdx`): explains how to add the starter as a Git remote, cherry-pick commits into downstream projects, use the `starter:check` command to discover new releases, and an alternative patch-file workflow

### Changed

- Audits table URL column now displays with a monospace font (`FontFamily::Mono`) and has a proper "URL" label
- OpenAPI schema annotations for API resources (`AccessTokenResource`, `PermissionResource`, `RoleResource`, `UserResource`): reordered `example` before `enum` properties to fix schema generation ordering issues
- Release workflow (`.github/workflows/release.yml`) updated to trigger the smoke test

## [v1.7.0] - 2026-03-02

### Added

- Agentless WebSSO (Online Passport) support as an alternative to Microsoft Entra ID for SSO authentication. New routes registered under `auth/websso/` prefix: `login-websso` and `login-websso-logout`
- `.env.example` now includes configuration variables for the agentless WebSSO provider: `WEBSSO_URL_BASE`, `WEBSSO_API_URL_BASE`, `WEBSSO_API_KEY`, and `DUO_ENABLED`

### Changed

- `LoginSelectionController` now determines the SSO route based on whether WebSSO is configured (via `WEBSSO_API_KEY` or `forgerock-direct` strategy). When WebSSO credentials are present, users are directed to the `login-websso` route; otherwise they are sent to the Entra ID `login-oauth-redirect` route
- `LogoutSelectionController` mirrors the same detection logic for logout, routing to either `login-websso-logout` or `login-oauth-logout` based on the active SSO provider
- `WebSSOController` constructor sets `login_route_name` to `login-websso` and `logout_return_to_route` to `login-selection`, and moved the `WebSSOAuthentication` trait alias to the top of the class body
- Login selection Blade view now receives the `ssoRoute` and `localEnabled` variables from the controller instead of computing them inline

### Fixed

- `LoginSelectionController` always redirected non-local-auth users to the Entra ID OAuth flow, even when the application was configured for agentless WebSSO. The controller now detects the active SSO strategy and redirects accordingly

## [v1.6.2] - 2026-03-02

### Changed

- Upgraded GitHub Actions artifact actions in the CI workflow (`check-pr.yml`): `actions/upload-artifact` from v6 to v7 and `actions/download-artifact` from v7 to v8
- Added `@var` type annotations to Eloquent Builder return values in `RoleResource`, `SupportTicketResource`, and `UserResource` to satisfy PHPStan
- Reordered `enum`/`example`/`nullable` property keys in the OpenAPI schema (`api-schema.yaml`) to follow a consistent attribute ordering convention
- Moved the health check route group below the protected API route group in `routes/api.php` and replaced the inline FQCN with an explicit `use` import for `HealthCheckJsonResultsController`
- Updated `composer.lock` with minor dependency version bumps

## [v1.6.1] - 2026-02-28

### Added

- `RAY_ENABLED=false` entry in `.env.example` for the Spatie Ray debugging tool

### Fixed

- `StarterCheckCommand` now catches `PDOException` in its `handle()` method and returns success, preventing `composer create-project` from failing when the database does not yet exist (e.g., on a fresh install before migrations have run)

## [v1.6.0] - 2026-02-28

### Added

- `RolePolicy::delete()` authorization gate requiring `DELETE_ROLES` permission before deleting a role
- Per-IP rate limiting on `SendLoginCodeController` (configurable via `local-auth.rate_limit_per_ip_per_hour`, default 20), preventing a single IP from exhausting multiple accounts' per-email rate limits (targeted account lockout DoS)
- Rate-limit slot consumption for unknown email addresses in `SendLoginCodeController`, eliminating response timing differentials that could enable user enumeration
- CSV formula injection protection (`sanitizeCsvFormula()`) across all Filament exporters: `ApiRequestLogExporter`, `AuditExporter`, `SupportTicketExporter`, and `UserLoginRecordExporter`. Values starting with `=`, `+`, `-`, `@`, tab, or carriage return are prefixed with a single quote
- Authorization checks on `UsersRelationManager` for role assignment and removal, calling `abort_unless($role->canBeManaged(), 403)` before attaching or detaching users
- Guard in `HandlesImpersonation::canBeImpersonated()` preventing users without `MANAGE_ALL` from impersonating users who hold that permission (privilege escalation prevention)
- Maximum expiration validation on `StoreAccessTokenRequest` (`max:` 10 years from now) to prevent creation of effectively-permanent API tokens
- `HEALTH_SECRET_TOKEN` environment variable added to `.env.example`; health check endpoint documentation updated to reflect it requires the `X-Secret-Token` header
- Test coverage for all new security behaviors: `SendLoginCodeControllerTest` per-IP and per-email rate limiting, `RolePolicyTest::delete`, `ProblemDetailsRendererTest` for `UnauthorizedHttpException`, `CreateLocalUserTest` retry logic, `HandlesImpersonationTest` privilege guard, `DownloadWildcardPhotoTest` strict base64 decoding

### Changed

- `AccessTokenApiController::show()` and `destroy()` now scope token lookup through the authenticated user's relationship (`$request->user()->access_tokens()->findOrFail($token)`) instead of route model binding with a manual `abort_if` ownership check. Returns 404 instead of 403 for tokens that don't belong to the requester
- `AccessToken::hashFromPlain()` now strips the `base64:` prefix from `APP_KEY` and decodes it before passing to `hash_hmac`, fixing HMAC computation when the key uses Laravel's default base64-encoded format
- `AccessToken::status` attribute now returns `REVOKED` when `token_hash` is null (orphaned or cleared tokens)
- Token session keys in `AccessTokenSchemas` split from a single `SESSION_KEY` into three distinct keys: `SESSION_KEY_CREATE`, `SESSION_KEY_CREATE_API_USER`, and `SESSION_KEY_ROTATE`, preventing cross-wizard session conflicts
- Raw access tokens are now encrypted with `Crypt::encryptString()` before being stored in the session and decrypted on read, preventing plaintext token exposure in session storage
- `CreateAccessTokenAction` and `CreateApiUserAction` now check `Session::has()` for their respective keys before re-executing token creation, preventing duplicate token generation on wizard step re-validation
- `CreateNorthwesternUserAction` validation now performs a lightweight `DirectorySearch::lookup()` instead of calling the full `FindOrUpdateUserFromDirectory` action, avoiding premature user creation during form validation
- `CreateLocalUser` wraps the database transaction in a `retry(3)` with `UniqueConstraintViolationException` handling for race conditions on username generation, and wraps the login challenge dispatch in a try/catch that reports failures instead of throwing
- `ProblemDetailsRenderer` now returns the `UnauthorizedHttpException` message and headers in the response instead of a generic unauthorized response
- `DirectorySearchCheck` health check no longer includes `tested_netid` in the `meta` array, removing PII from health check output
- `MakeChangelogCommand` date validation now catches `InvalidFormatException` instead of checking `instanceof Carbon`
- `SendAccessTokenExpirationNotificationsCommand` refactored to use `lazyById(100)` cursor iteration instead of loading all matching tokens into memory at once
- `ProcessNetIdUpdate` listener now acquires a `lockForUpdate()` on the user record inside the transaction to prevent concurrent update races
- `DownloadWildcardPhotoJob` now uses `strict: true` on `base64_decode` and validates the decoded result before storing, returning early on invalid photo data
- `ContactController` flash message changed from `status-success` to `status-danger` when both primary and fallback ticket submission fail
- Audit table event filter changed from a dynamic database-derived distinct query to a static options array (`created`, `updated`, `deleted`, `restored`, `role_assigned`, `role_removed`, `permissions_modified`)
- `AuditsTable::modelTypeOptionsGrouped()` wrapped in `once()` for per-request memoization, and filter option closures deferred with `fn ()` to avoid eager database queries during table construction
- System permissions in `CreateRole` and `EditRole` pages are now restricted to users with the `MANAGE_ALL` permission; non-super-admins cannot assign or remove system-managed permissions, and existing system permissions are preserved on edit
- `TelescopeServiceProvider` now hides the `authorization` header in request details to prevent Bearer token leakage in Telescope logs

## [v1.5.2] - 2026-02-27

### Changed

- Default cache store changed from `database` to `file` in both `config/cache.php` and `.env.example`. Avoids a `PDOException` on fresh installs before migration
- Release workflow commit message now includes `[skip ci]` suffix to prevent recursive CI triggers on version bump commits
- Documentation references updated: corrected `NetIdUpdateController` namespace path from `App\Http\Controllers\Webhooks` to `App\Domains\User\Http\Controllers\Webhooks`, fixed Filament version reference from "4" to "5", updated `PermissionEnum` file path from `User/Enums` to `Auth/Enums`, corrected `AuthTypeEnum` and `IssueLoginChallenge` import paths, fixed environment lockdown exempted routes documentation to reference `config/platform.php` instead of the removed `EXEMPTED_ROUTES` constant, added missing Support and Foundation domains to DDD file tree, and corrected CI/test environment wording for WebSSO logout behavior
- Health check documentation updated to list the actual registered checks (Database, Queue, Cache, Schedule, Debug Mode, Optimized App, Security Advisories, Directory Search) instead of the prior incorrect list
- Authorization documentation corrected: `ACCESS_ADMINISTRATION_PANEL` and `MANAGE_IMPERSONATION` marked as system-managed, `ACCESS_ADMINISTRATION_PANEL` removed from the default Northwestern User role permissions

### Fixed

- Database cache store threw `PDOException` on fresh installs before migrations. Changed default to `file` store, which works without database access

## [v1.5.1] - 2026-02-27

### Added

- `StarterCheckCommand` (`php artisan starter:check`): reads the current version from `.starter-version.yaml`, queries the GitHub Releases API for newer versions, and displays release notes with a compare URL; results are cached for 4 hours; automatically runs via `composer install` and `composer update` hooks in local environments; skipped in production, staging, QA, develop, and CI
- `RecordLogin` action class that captures login metadata (segment, IP address, user agent) and is now called from both `WebSSOController` and `VerifyLoginCodeController`, replacing inline login recording logic
- GitHub Actions release workflow (`.github/workflows/release.yml`) with `workflow_dispatch` trigger: validates semver format, prevents duplicate and non-ascending tags, bumps `.starter-version.yaml`, commits, tags, pushes, and creates a GitHub release with auto-generated notes
- MIT License file (`LICENSE`) and `license: MIT` field in `composer.json`
- Documentation pages: "Framework Defaults" (Eloquent behavior, auth, HTTP/security, Filament defaults, error handling), "Authorization" (roles, permissions, policies), "Adding a Filament Panel" guide, "Directory Search" integration reference
- Chart description Blade component (`chart-description.blade.php`) for rendering metric summaries beneath API request chart widgets

### Changed

- API request duration chart widget (`ApiRequestDurationChartWidget`) replaced "Max Duration" series with "P95 Duration" using `PERCENTILE_CONT(0.95)` SQL aggregate, added a dashed "Slow Threshold" reference line (configurable via `api.request_logging.slow_request_threshold_ms`), and color-codes P95 data points red when they exceed the threshold
- API requests by status chart widget (`ApiRequestsByStatusChartWidget`) converted chart options from a PHP array to `RawJs` and added tooltip callbacks showing request counts with percentage of total for each status category
- `VerifyLoginCodeController` refactored: extracted `decryptChallengeId()`, `resolveChallenge()`, and `authenticateUser()` private methods from `__invoke()`; login recording delegated to the new `RecordLogin` action
- `ImpersonationController` refactored: extracted `storeReturnUrl()` and `resolveRedirect()` private methods, and `leave()` now uses the same redirect resolution logic as `take()`
- `EnvironmentLockdown` middleware moved the hardcoded `EXEMPTED_ROUTES` constant into `config('platform.lockdown.exempted_routes')`, making exempted routes configurable without modifying middleware source code
- `config/platform.php` reorganized: added `default_user_timezone` setting, moved lockdown config to its own section with the new `exempted_routes` array, and added `wildcard_photo_sync` toggle
- Platform overview page sections use Filament's `heading` and `icon` attributes on `<x-filament::section>` instead of composed heading slots
- Login records table and user infolists now display IP address and user agent columns

## [v1.5.0] - 2026-02-25

### Added

- Interactive JSON diff viewer for audit log entries using the `@pierre/diffs` library, rendered via an Alpine.js component (`auditDiffViewer`) with split/unified view modes, word-level diffs, and dark mode support. Includes a custom Vite plugin (`shikiMinimalBundle`) that replaces Shiki's full language/theme bundles with minimal stubs, reducing bundle size from ~200 language chunks to JSON only
- Record history timeline view on audit log detail pages (`record-timeline.blade.php`) showing the complete change history for a given auditable record
- `SupportTicketExporter` for CSV export of support ticket data from the Filament admin panel
- `ValidIpOrCidrRule` validation rule supporting both individual IP addresses (v4/v6) and CIDR ranges with proper prefix length validation
- `Auditable` trait enhanced with `auditCustomTags` and `auditCustomContext` support, allowing audit trait compositions (e.g., `AuditsRoles`) to attach structured metadata tags to audit entries
- `RateLimitingServiceProvider` with named rate limiters for API requests (`api`), login code request/verify flows (`auth:login-code:request`, `auth:login-code:verify`), impersonation (`auth:impersonate`), and support contact form (`support:contact`), each with configurable thresholds
- `config/api.php` with settings for API enable/disable toggle, auth realm, request logging (slow request threshold, retention days, sampling rate), expiration notification intervals, and demo user token
- `config/local-auth.php` extracted from inline configuration with settings for local auth enable/disable, fixed verification codes, rate limits, redirect destination, and code parameters (digits, expiry, max attempts, lockout, cooldown, retention)
- `config/rate-limiting.php` with per-route rate limit values for API, auth, impersonation, and support flows
- `config/platform.php` expanded with production URL, app name/abbreviation, datetime display format, user timezone, and wildcard photo sync settings
- Documentation pages for Northwestern integrations: Directory Search, EventHub, WebSSO, Wildcard Photos, and a hub index page
- Test coverage additions: `AuditableTest`, `SupportTicketConfirmationTest`, `SupportTicketMessageTest`, `TeamDynamixCacheRepositoryTest`, `TeamDynamixGatewayTest`, `ChangelogControllerTest`, `ChangelogFeedControllerTest`, `ContactFormRequestTest`, `WildcardPhotoTest`, `UserRoleAccessTest`, `GateSuperAdminBypassTest`, `RotateAccessTokenTest`, `StartImpersonationTest`, `ClipboardTest`, `FixedNumericOneTimeCodeGeneratorTest`, `RandomNumericOneTimeCodeGeneratorTest`, `TokenExpirationEnumTest`, `SchemaFileCollectionTest`, `SchemaSnapshotTest`, `SeederInfoTest`, `SnapshotListItemTest`, `ValidIpOrCidrRuleTest`, `IdempotentSeederResolverTest`, and others
- PHPUnit test coverage reporting via Coveralls in CI, with sharded coverage collection and merge

### Changed

- Framework config files `config/app.php`, `config/auth.php`, and `config/session.php` replaced with dedicated starter config files (`config/api.php`, `config/local-auth.php`, `config/platform.php`, `config/rate-limiting.php`), extracting application-specific settings from Laravel framework defaults
- `StoreAccessTokenRequest` validation rules enhanced: `expires_at` now requires a minimum of 1 day in the future, and `allowed_ips` items validated with `ValidIpOrCidrRule`
- PHPStan level increased with additional type annotations across the codebase
- CI workflow (`check-pr.yml`) optimized: common setup action refactored, coverage report templates added, sharded test execution
- Test database seeding strategy changed from per-test to per-process for performance
- Filament audit infolist (`AuditInfolist`) redesigned with restructured layout
- Filament chart widgets converted from PHP array options to `RawJs` for JavaScript-level tooltip and interaction customization

## [v1.4.0] - 2026-02-11

### Added

- Support ticketing system with gateway abstraction: `TicketSystemGateway` contract, `TicketSystemGatewayFactory`, `TicketSystemEnum` (`team-dynamix` and `mail` drivers), and `CreateSupportTicket` action with automatic mail fallback when the primary gateway fails
- `TeamDynamixGateway` for submitting tickets to TeamDynamix (TDX) via its REST API, with `TeamDynamixCacheRepository` for caching TDX metadata lookups (ticket types, forms, statuses, services, priorities)
- `MailGateway` for email-based ticket submission, sending both a `SupportTicketMessage` to the support team and a `SupportTicketConfirmation` to the requester, with auto-generated reference numbers (e.g., `SUP-47`)
- `SupportTicket` model with `SupportTicketRepository` for persistence, tracking ticketing system, ticket number, delivery status, error messages, and fallback timestamps
- `SupportTicketResource` Filament resource with list and view pages, `SupportTicketInfolist` detail view, `SupportTicketsTable` with filtering and search, and `SupportTicketSubmissionLogBanner` widget
- `ContactController` and `ContactFormRequest` for the public-facing `/support/contact` form (requires authentication)
- `VIEW_SUPPORT_TICKETS` permission added to `PermissionEnum` as a system-managed permission
- `config/support.php` with driver selection (`SUPPORT_DRIVER`), TeamDynamix API credentials and metadata mappings, mail gateway settings (recipient, from address, reference prefix), and feature toggle
- Changelog system: `Changelog` model backed by Markdown files in `resources/changelogs/`, synced to the database via `ChangelogSeeder` on each deployment
- `ChangelogController` and `ChangelogFeedController` with public web routes at `/support/changelog` and `/support/changelog/feed.rss`
- `make:changelog` Artisan command for scaffolding new changelog Markdown files with YAML front matter, interactive prompts for slug/date/title, automatic deduplication for conflicting filenames, and a stub template
- `MarkdownWithJiraLinksCast` Eloquent cast that transforms backtick-wrapped JIRA issue identifiers (e.g., `` `PROJ-1234` ``) into clickable links using configurable identifier prefix and base URL from `config/changelog.jira.*)`
- `config/changelog.php` with settings for enabling the changelog, Jira integration, and Markdown rendering options
- `SupportServiceProvider` registering the `TicketSystemGateway` binding and `SupportTicketRepository`
- Database migrations for `changelogs` and `support_tickets` tables
- Tests: `CreateSupportTicketTest`, `TicketSystemGatewayFactoryTest`, `MailGatewayTest`, `ContactControllerTest`, `SupportTicketRepositoryTest`, and `MarkdownWithJiraLinksCastTest`
- Documentation pages for the changelog feature and support ticketing system

## [v1.3.1] - 2026-02-05

### Added

- Date range preset filter on the `ApiRequestLogsTable` with "Today", "Last 7 Days", "Last 30 Days", and "Last 90 Days" options
- Resource descriptions (`$description`) on `UserResource`, `RoleResource`, `AuditResource`, `UserLoginRecordResource`, and `ApiRequestLogResource` for contextual help text in the admin panel
- Empty state headings, descriptions, and icons on all major Filament tables (`UsersTable`, `RolesTable`, `AuditsTable`, `ApiRequestLogsTable`, `UserLoginRecordsTable`) when no records exist
- Filter trigger buttons (styled as `->button()`) on the Roles and Login Records tables for a more consistent filter UI
- `Vite::useAggressivePrefetching()` in `AppServiceProvider` for faster frontend asset loading
- `Http::preventStrayRequests()` to guard against unintended outbound HTTP calls during development and testing
- OTP input responsive breakpoints at 420px and 340px in the `otp.blade.php` component for small-screen devices
- Purple focus ring styles (`box-shadow`) on links, buttons, form controls, and OTP inputs for WCAG-compliant keyboard navigation indicators
- Loading spinner animation on the login code verify button (icon changes to `fa-circle-notch fa-spin`, text changes to "Verifying...")
- Badge-style resend countdown timer on the login code verification form (replaces plain text)

### Changed

- `ApiRequestLogResource` navigation label renamed from "Activity" to "API Requests"; slug changed from `activity` to `requests`
- Users table "Auth Type" column and filter renamed to "Authentication"
- `AdministrationPanelProvider` developer tools navigation items now open in new tabs, with visibility guards (MinIO requires `minio_console` config to be filled; MailPit requires `viewTelescope` permission); MinIO renamed to "MinIO Console"; MailPit icon changed from `OutlinedInbox` to `OutlinedEnvelope`
- Button hover color for `.btn-primary` changed from `#b6acd1` (light purple) to `$purple-dark` for better contrast
- Button transition CSS standardized to `color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out`
- `AppServiceProvider::configureExceptions()` renamed to `configureRequests()` and expanded to include `Http::preventStrayRequests()`

### Fixed

- Decoy challenge ID handling in `ShowLoginCodeFormController` and `VerifyLoginCodeController` now uses `ctype_digit()` to check if the challenge ID is numeric before querying the database, preventing SQL errors when non-numeric UUID decoy values (stored for non-existent users to prevent timing enumeration) are passed to `LoginChallenge::find()`

## [v1.3.0] - 2026-02-03

### Added

- `TracksPermissionSources` trait on the `User` model providing `hasPermissionFromRole()`, `getPermissionsFromRole()`, and `getRolesGrantingPermission()` methods for fine-grained permission source checking, useful when authorization logic depends on which specific role grants a permission
- New `Platform\Overview` page replacing the old `ConfigurationPage`, displaying environment info (PHP/Laravel version, lockdown mode), services (database, cache, queue, session, broadcasting, mail), storage details (S3 bucket, region, endpoint), observability settings (Sentry DSN and sample rates), external integrations (EventHub, Directory Search with live/mock status), and a live health check dashboard powered by `EloquentHealthResultStore`
- `create_health_tables` migration for `health_check_result_history_items` to support persistent health check result storage
- `--force` flag on `db:snapshot:restore` to skip the confirmation prompt (useful for CI)
- Automatic permission cache clearing step after snapshot restore via `PermissionRegistrar::forgetCachedPermissions()`
- Dependabot grouping configuration for patch and minor updates across Composer, npm, npm/docs, and GitHub Actions ecosystems

### Changed

- Health check result store switched from `InMemoryHealthResultStore` to `EloquentHealthResultStore` with 5-day retention, enabling the new Platform Overview health dashboard to display historical results
- Replaced `fruitcake/laravel-telescope-toolbar` with `fruitcake/laravel-debugbar` (`^4.0`) for local development debugging; removed associated Telescope toolbar accessibility CSS overrides from `app.scss`
- `AppServiceProvider` refactored: rate limiting logic extracted from `configureRoutes()` into a dedicated `configureRateLimiting()` method; login code verification rate limiter simplified to use decrypted challenge key directly instead of `md5`-hashed fallback
- All auth controllers (`SendLoginCodeController`, `ShowLoginCodeFormController`, `VerifyLoginCodeController`, `LogoutSelectionController`, `ImpersonationController`, `WebSSOController`) refactored from `session()` helper to `Session` facade for consistency
- `VerifyLoginCodeController` now calls both `Session::regenerate()` and `Session::regenerateToken()` after login (before, only the session was regenerated)
- Rector configuration expanded: added `SetList::PHP_84`, `SetList::CODE_QUALITY`, `SetList::DEAD_CODE`, `SetList::EARLY_RETURN`, `SetList::TYPE_DECLARATION` rule sets

### Removed

- `Filament\Pages\Platform\ConfigurationPage` and its Blade template `configuration-page.blade.php`, replaced by the `Platform\Overview` page

### Fixed

- `login-code-verify` rate limiter falls back to IP address when challenge ID cannot be decrypted
- Session regeneration after login now also regenerates CSRF token
- Database snapshot restore no longer silently skips in non-interactive CI
- Permission cache clearing after database snapshot restore

## [v1.2.0] - 2026-01-23

### Added

- **Filament data export system**: five new `Exporter` classes (`ApiRequestLogExporter`, `AuditExporter`, `RoleExporter`, `UserExporter`, `UserLoginRecordExporter`) enabling CSV export from all major admin tables; exports are stored on S3 and auto-pruned after 7 days via the `Export` model's `prunable()` method
- `ApiCluster` Filament cluster grouping API-related pages under a dedicated "API" navigation section in the Platform group, with an `Overview` page displaying API configuration, active tokens, expiring tokens, 24-hour request volume, success rates, average response times, rate limits, and notification settings
- `WelcomeWidget` for the Filament dashboard
- `Export` model (`App\Domains\User\Models\Export`) extending Filament's base export model with automatic pruning of exports older than 7 days and S3 file cleanup
- Database migrations for `notifications`, `imports`, `exports`, and `failed_import_rows` tables to support Filament's notification and import/export infrastructure
- Database notifications enabled on the admin panel with 30-second polling
- Role delete confirmation modal now shows a dynamic warning with affected user count (e.g., "This role is assigned to 3 users") before deletion
- Health checks documentation page covering registered checks, API endpoint, notifications, result storage, and custom check creation
- `.env.example` additions for `HEALTH_NOTIFICATIONS_ENABLED` and `HEALTH_NOTIFICATION_EMAIL`

### Changed

- Administration panel `maxContentWidth` set to `Width::Full` for a wider layout
- Navigation group renamed from `AdministrationNavGroup::DEBUG` to `AdministrationNavGroup::DEVELOPER_TOOLS`
- Filament cluster auto-discovery enabled via `discoverClusters()` in `AdministrationPanelProvider`
- `RunsSteps` trait: step failures now render full exception stack traces, not just the message
- API request logs resource now uses the `ApiCluster` as its parent cluster
- Health config: notifications now configurable via `HEALTH_NOTIFICATIONS_ENABLED` env var; notification email uses `HEALTH_NOTIFICATION_EMAIL` env var instead of hardcoded placeholder

## [v1.1.0] - 2026-01-13

### Added

- `.starter-version.yaml` file to track the upstream starter template version (initially set to `1.1.0`), enabling downstream projects to compare against newer releases
- `RunsSteps` trait (`App\Console\Commands\Concerns\RunsSteps`) providing a reusable step-by-step command execution framework with spinner animations, pass/fail tracking per step, and a summary display
- `DeleteDatabaseSnapshotCommand` (`db:snapshot:delete`) for removing individual snapshots or all snapshots at once (`--all`), including cleanup of associated metadata from the checksum map
- `InfoDatabaseSnapshotCommand` (`db:snapshot:info`) for displaying detailed snapshot information including file path, size, creation timestamp, schema checksum, migration/seeder counts, and comparison with current codebase schema
- `AppKeyValidator` config validator checking that `APP_KEY` is set, starts with `base64:`, and decodes to at least 32 bytes
- `EventHubValidator` config validator checking EventHub credentials (`EVENT_HUB_BASE_URL`, `EVENT_HUB_API_KEY`, `EVENT_HUB_HMAC_VERIFICATION_SHARED_SECRET`) when mock mode is disabled, auto-passing when `EVENT_HUB_MOCK_ENABLED=true`
- `ConfigValidator` interface expanded with `name(): string` for human-readable validator labels and `hints(): array` for actionable remediation suggestions shown on failure

### Changed

- Node.js version bumped from 24 to 25 across `.nvmrc`, GitHub Actions workflows, CI configuration, and documentation
- Frontend dependency upgrades: Vite 6 to 7, `@sentry/browser` 9 to 10, `@fortawesome/fontawesome-free` 6 to 7, Cypress 14 to 15, `laravel-vite-plugin` 1 to 2
- FontAwesome 7 integration: SCSS imports changed from `@import` to `@use` module syntax with explicit `$font-path` configuration; added `.sr-only` class (removed in FontAwesome 7 but needed for backward compatibility)
- GitHub Actions: `actions/cache` upgraded from v4 to v5, `actions/upload-artifact` from v5 to v6, `actions/download-artifact` from v6 to v7
- `CreateDatabaseSnapshotCommand` rewritten to use `RunsSteps` trait: removed `--force` flag, added `--skip-schema-validation` flag; blocks execution in production
- `RestoreDatabaseSnapshotCommand` rewritten to use `RunsSteps` trait: added `--backup` flag to auto-create a backup before restoring; added `--skip-schema-validation` flag; interactive snapshot selection when no name provided
- `RebuildDatabaseCommand` rewritten to use `RunsSteps` trait with spinner-based execution
- `ValidateConfigurationCommand` rewritten to use `RunsSteps` with per-validator step display showing name, pass/fail status, and failure hints
- All existing config validators (`DatabaseValidator`, `EnvironmentVariablesValidator`, `FilesystemValidator`, `QueueValidator`) refactored to implement the new `name()` and `hints()` methods
- Documentation sidebar ordering switched from `order` frontmatter key to `sidebar.order` object syntax across all doc pages

## [v1.0.0] - 2026-01-08

First stable release. For installation, configuration, and usage guides, visit the [documentation](https://laravel-starter.entapp.northwestern.edu).

### Changed

- Default `SESSION_LIFETIME` in `.env.example` increased from `120` (2 hours) to `480` (8 hours) to better accommodate typical workday sessions
- Removed `flowframe/laravel-trend` package from Composer dependencies
- Added `@php artisan ide-helper:models -N` to the `post-update-cmd` Composer script for automatic model PHPDoc generation
- Moved `sass` from devDependencies to dependencies in `package.json` and consolidated duplicate entries
- README cleaned up: removed emoji prefixes from section headings; removed the "System Requirements" section (now covered in documentation); simplified to point to the documentation site

## [v0.5.0] - 2025-12-28

### Added

- New `Auth` domain (`app/Domains/Auth/`) as a dedicated bounded context, extracting all authentication and authorization concerns out of the `User` domain. The Auth domain now owns models (`AccessToken`, `ApiRequestLog`, `LoginChallenge`, `Permission`, `Role`, `RoleType`), enums, HTTP controllers, middleware, policies, seeders, and mail notifications.
- `OneTimeCodeGenerator` contract with two implementations: `RandomNumericOneTimeCodeGenerator` for production use and `FixedNumericOneTimeCodeGenerator` (generates deterministic `123456...` sequences) for CI environments. The appropriate implementation is bound as a singleton in `AppServiceProvider` based on the running environment.
- Consolidated `SendLoginCodeController` in the Auth domain that handles both initial code sends and resends in a single controller. Implements timing-based user enumeration protection using Laravel's `Timebox` with a minimum 500ms response time plus random jitter.
- `VerifyLoginCodeController` in the Auth domain with encrypted challenge ID decryption, database-level locking (`lockForUpdate`) during code verification, and lockout duration messaging.
- Cypress end-to-end test suites for authentication: `login.cy.ts` covering login selection, local login code request, OTP code entry with auto-submit, and invalid code rejection; `logout.cy.ts` covering session clearing and protected page access after logout.
- Per-challenge rate limiting on login code verification, keyed by both IP address and the encrypted challenge ID.

### Changed

- All authentication and authorization code relocated from `App\Domains\User` and `App\Http\Controllers` to `App\Domains\Auth`. The `User` domain now focuses exclusively on user data management, directory integration, and user-specific concerns.
- Impersonation routes changed from `GET` to `POST` method for both `impersonate/take` and `impersonate/leave`, improving CSRF protection.
- Impersonation "Leave Impersonation" links converted from `<a>` tags to `<form>` POST submissions with CSRF tokens.
- Documentation filenames switched from numeric-prefix ordering (`01-introduction.mdx`) to descriptive names (`introduction.mdx`) with explicit `order` frontmatter properties for sidebar sorting. Emoji prefixes removed from all documentation sidebar labels.

## [v0.4.0] - 2025-12-23

### Added

- Email OTP verification login flow replacing the previous magic link system. New controllers: `SendLoginCodeController`, `ShowLoginCodeFormController`, `ShowLoginCodeRequestController`, `VerifyLoginCodeController`, and `ResendLoginCodeController`. New actions: `IssueLoginChallenge`, `VerifyLoginChallengeCode`, and `GenerateOneTimeCode`.
- `LoginChallenge` model replacing `UserLoginLink`, with fields for hashed verification codes, attempt tracking, lockout support (`locked_until`), and expiration.
- `LoginCodeSession` value object centralizing session key constants used across the login code flow.
- `SendLoginCodeEmailJob` queued job for dispatching verification code emails, replacing the synchronous magic link approach.
- OTP input Blade component (`resources/views/components/otp.blade.php`) built with Alpine.js, supporting configurable digit length, numeric-only mode, auto-advance between fields, paste handling, separator display, keyboard navigation, and automatic form submission on completion.
- Full REST API endpoints for access token and user management: `AccessTokenApiController` (list, create, show, rotate, revoke tokens), `UserApiController` (show authenticated user with roles and permissions).
- API resource classes: `AccessTokenResource`, `PermissionResource`, `RoleResource`, and `UserResource` under `App\Http\Resources\Api\V1` with full OpenAPI attribute annotations.
- `ProblemDetails` response class implementing RFC 9457 structured error responses for the API.
- OpenAPI schema definition (`docs/schemas/api-schema.yaml`) documenting all API v1 endpoints with request/response schemas.
- Authentication configuration expanded with `code` section: `digits` (6), `expires_in_minutes` (10), `max_attempts` (8), `lock_minutes` (15), and `resend_cooldown_seconds` (30).

### Changed

- Login selection view updated text from "Magic Link" references to "Email" sign-in.
- `EnvironmentLockdown` middleware exempted routes updated from `login-link.*` to `login-code.*` pattern.
- API routes reorganized with protected endpoints for token management (`/api/v1/me/tokens`) and user profile (`/api/v1/me`).

### Removed

- Magic link authentication system: `SendLoginLink` and `ValidateLoginLink` actions, `LoginLinkController`, `LoginLinkNotification` mailable, `UserLoginLink` model, and `user_login_links` migration.

## [v0.3.0] - 2025-12-10

### Added

- `AccessToken` model replacing `ApiToken`, with `token_prefix` encryption, `AccessTokenStatusEnum` (Active/Expired/Revoked with Filament color and icon support), `orderByRelevance` and `active` query scopes, and `rotated_from_token`/`rotated_by_user` relationships for rotation tracking.
- `TokenExpirationEnum` providing predefined expiration periods (1 day through 1 year, plus "No Expiration") with human-readable labels and `expiresAt()` date calculation.
- `PermissionScopeEnum` distinguishing between `SYSTEM_WIDE` and `PERSONAL` permission scopes, with Filament badge rendering.
- `RoleModificationOriginEnum` tracking the source of role changes: `UI_ACTION`, `REMOVED_BY_DELETION`, and `NETID_STATUS_CHANGE`.
- `SystemRoleEnum` defining `SUPER_ADMINISTRATOR` and `NORTHWESTERN_USER` as non-modifiable system roles.
- `EnvironmentLockdown` middleware restricts application access in non-production environments to users with roles beyond the default "Northwestern User" role. Configured via `platform.lockdown.enabled`.
- NetID update webhook system: `NetIdUpdateController` processing webhook payloads from Northwestern's Identity system, `NetIdUpdated` event, and `ProcessNetIdUpdate` queued listener that removes all non-default roles and marks the user's NetID as inactive.
- `TestNetIdUpdateCommand` (`php artisan netid:update:test`) for simulating NetID webhook messages during development.
- `AuditsPermissions` trait providing `syncPermissionsWithAudit()` with detailed before/after permission diffs in audit logs.
- `AuditsRoles` trait updated to accept single or multiple roles with a `RoleModificationOriginEnum` origin and optional context array for audit trail enrichment.
- Permission definitions reorganized in `PermissionEnum` with logical groupings and renamed permissions (`ACCESS_ADMIN_PANEL` to `ACCESS_ADMINISTRATION_PANEL`, `MODIFY_ROLES` to `EDIT_ROLES`, `MANAGE_USER_ROLES` to `ASSIGN_ROLES`).
- Documentation site built with Starlight: architecture guides, feature documentation, getting started guides, deployment guide, and reference documentation.
- Documentation deployment workflow (`.github/workflows/deploy-docs.yml`).

### Changed

- `ApiToken` model renamed to `AccessToken` throughout the codebase, including Filament resources, factories, actions, middleware, commands, and notifications.
- User actions reorganized into subdirectories: `Api/`, `Directory/`, `Impersonation/`, and `Local/`.
- `users` database migration updated with `netid_inactive` boolean column.
- `ApiRequestLog` model updated with configurable pruning retention (`auth.api.request_logging.retention_days`, default 90 days).
- `ImpersonateEvent` listener renamed to `LogImpersonationAccess`.

### Fixed

- Model pruning for `ApiRequestLog` corrected to use the `MassPrunable` trait with proper retention day configuration.

## [v0.2.0] - 2025-11-24

### Added

- Redesigned 500 error page with conditional exception detail visibility: non-production environments display full exception details; production restricts to users with `MANAGE_ALL` permission. Includes an embedded Sentry feedback form.
- `#[AutomaticallyOrdered]` PHP attribute replacing the previous scope class, supporting configurable primary/secondary sort columns and directions.
- `UserBuilder` custom query builder with typed scopes: `sso()`, `local()`, `api()`, `whereEmailEquals()`, `searchByName()`, `firstSsoByEmail()`, `firstLocalByEmail()`, `firstExistingByEmailOrNewSso()`, and `firstExistingSsoByNetIdOrNew()`.
- `FindOrUpdateUserFromDirectory` action replacing `CreateUserByLookup`, with `PersistUserWithUniqueUsername` for safe concurrent user creation via `UniqueConstraintViolationException` handling.
- `AuditsRoles` trait providing `assignRoleWithAudit()` and `removeRoleWithAudit()` with complete before/after role snapshots in custom audit events.
- `AuditsPermissions` trait providing `syncPermissionsWithAudit()` with permission diff tracking (added/removed).
- `MissingRequestIpForRestrictedToken` exception reported when an IP-restricted API token receives a request without a client IP address.
- Polling support added to API Request Log Filament widgets for automatic data refresh.
- Test suite expansion covering commands, models, actions, policies, controllers, and middleware.

### Changed

- `UserRepository` removed; all user queries now use `UserBuilder` methods or Eloquent query builder.
- `LoginLink` model renamed to `UserLoginLink` for consistency with the database table name.
- `ImpersonationController` moved from `App\Http\Controllers\Admin` to `App\Http\Controllers\Auth`.
- Session encryption enabled by default (`config/session.php` `encrypt` changed from `false` to `true`).
- `AuthenticatesApiTokens` middleware hardened: raw token variable `unset()` after hashing; IP allowlist check handles missing request IPs; token usage tracking simplified to `increment()`.
- `SentryExceptionHandler` guards against early bootstrap errors by checking resolved guards before accessing the authenticated user.

### Fixed

- Open redirect vulnerability in `ImpersonationController`: the referer-based return URL is now validated against the application's configured host. An explicit `MANAGE_IMPERSONATION` permission check was also added.
- Session fixation risk in `LoginLinkController` mitigated by wrapping login link verification in a database transaction.
- Sentry exception handler crash during early application bootstrap when authentication guards had not yet been resolved.
- Model pruning for `ApiRequestLog` records not functioning due to incorrect console schedule configuration and missing `MassPrunable` trait.

### Removed

- `spatie/laravel-ignition` package dependency.

## [v0.1.0] - 2025-11-18

### Added

- Initial release of the Northwestern Laravel Starter, an enterprise Laravel application template.
- **Domain-Driven Design architecture** with two domains: `Core` (base models, enums, exceptions, health checks, database utilities, seeding infrastructure) and `User` (user management, authentication, authorization, audit logging, API tokens).
- **Multi-method authentication system**: WebSSO/Entra ID single sign-on via `WebSSOController`, passwordless magic link login via `LoginLinkController` (configurable via `auth.local.enabled`), and Bearer token authentication for API consumers via `AuthenticatesApiTokens` middleware.
- **Role-based access control** using Spatie Permission with custom `Role` and `Permission` models, `RoleType` classification, Filament-based role management, and user role assignment through relation managers.
- **Audit logging** via `owen-it/laravel-auditing` with custom `Auditable` concern on `BaseModel`, dedicated `Audit` model, and Filament audit resource with infolist display.
- **API token management system**: `ApiToken` model with HMAC-SHA256 hashing, IP allowlist support, usage tracking, token rotation, revocation, and Filament UI.
- **API request logging**: `ApiRequestLog` model recording endpoint, method, status, duration, and failure reasons; `LogsApiRequests` middleware; configurable sampling; Filament resource with chart widgets and date range filtering.
- **API token expiration notifications**: `SendApiTokenExpirationNotificationsCommand` scheduled command sending email reminders at configurable intervals.
- **User impersonation**: `StartImpersonation` and `StopImpersonation` actions, `ImpersonationController`, `ImpersonationLog` model, and Filament impersonation banner.
- **Northwestern Directory Search integration**: `CreateUserByLookup` action, `SyncUserFromDirectory`, `DirectorySearchCheck` health check, and `DownloadWildcardPhotoJob` for ID photo caching.
- **User segmentation**: `DetermineUserSegment` action and `UserSegmentEnum` classifying users by affiliation for login trend analytics.
- **Filament administration panel** with resources for Users, Roles, Audits, API Request Logs, and User Login Records; `ConfigurationPage`; login trend and API analytics chart widgets.
- **RFC 9457 Problem Details error responses** via `ProblemDetailsRenderer` for API endpoints.
- **Database snapshot system**: `CreateDatabaseSnapshotCommand`, `RestoreDatabaseSnapshotCommand`, `ListDatabaseSnapshotsCommand` with schema checksum tracking.
- **Idempotent seeding infrastructure**: `IdempotentSeeder` base class, `#[AutoSeed]` attribute, `AutoSeedListCommand` for production-safe, rerunnable database seeders.
- **Configuration validation**: `ValidateConfigurationCommand` with pluggable validators (`DatabaseValidator`, `EnvironmentVariablesValidator`, `FilesystemValidator`, `QueueValidator`).
- **Custom error pages** (401, 402, 403, 404, 419, 429, 500, 503, database-paused) with Northwestern branding.
- **Custom Blade components**: `<x-breadcrumbs>`, `<x-clipboard>`, `<x-default-profile-photo>`, `<x-select>` (with Tom Select), `<x-tooltip>`, `<x-wildcard-photo>`.
- **CI pipeline**: GitHub Actions workflow with PHP/Node setup, database provisioning, Pest and Cypress test execution; Dependabot configuration.
- **Developer tooling**: `.editorconfig`, `.prettierrc`, `.nvmrc` (Node v24), custom stubs, Rector configuration.

[Unreleased]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v2.6.0...HEAD
[v2.6.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v2.5.0...v2.6.0
[v2.5.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v2.4.0...v2.5.0
[v2.4.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v2.3.0...v2.4.0
[v2.3.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v2.2.0...v2.3.0
[v2.2.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v2.1.3...v2.2.0
[v2.1.3]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v2.1.2...v2.1.3
[v2.1.2]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v2.1.1...v2.1.2
[v2.1.1]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v2.1.0...v2.1.1
[v2.1.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v2.0.0...v2.1.0
[v2.0.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.17.0...v2.0.0
[v1.17.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.16.0...v1.17.0
[v1.16.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.15.2...v1.16.0
[v1.15.2]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.15.1...v1.15.2
[v1.15.1]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.15.0...v1.15.1
[v1.15.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.14.0...v1.15.0
[v1.14.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.13.3...v1.14.0
[v1.13.3]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.13.2...v1.13.3
[v1.13.2]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.13.1...v1.13.2
[v1.13.1]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.13.0...v1.13.1
[v1.13.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.12.0...v1.13.0
[v1.12.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.11.0...v1.12.0
[v1.11.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.10.0...v1.11.0
[v1.10.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.9.2...v1.10.0
[v1.9.2]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.9.1...v1.9.2
[v1.9.1]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.9.0...v1.9.1
[v1.9.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.8.0...v1.9.0
[v1.8.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.7.2...v1.8.0
[v1.7.2]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.7.1...v1.7.2
[v1.7.1]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.7.0...v1.7.1
[v1.7.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.6.2...v1.7.0
[v1.6.2]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.6.1...v1.6.2
[v1.6.1]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.6.0...v1.6.1
[v1.6.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.5.2...v1.6.0
[v1.5.2]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.5.1...v1.5.2
[v1.5.1]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.5.0...v1.5.1
[v1.5.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.4.0...v1.5.0
[v1.4.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.3.1...v1.4.0
[v1.3.1]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.3.0...v1.3.1
[v1.3.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.2.0...v1.3.0
[v1.2.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.1.0...v1.2.0
[v1.1.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v1.0.0...v1.1.0
[v1.0.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v0.5.0...v1.0.0
[v0.5.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v0.4.0...v0.5.0
[v0.4.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v0.3.0...v0.4.0
[v0.3.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v0.2.0...v0.3.0
[v0.2.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v0.1.0...v0.2.0
[v0.1.0]: https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/releases/tag/v0.1.0
