# Agent guide

How to work in this repository. It starts as the Northwestern Laravel Starter, and an
application built from it keeps this file: update it as the application grows.

Detailed coding standards (PHP style, models, migrations, factories, Blade, Filament,
accessibility) are in [`.github/copilot-instructions.md`](.github/copilot-instructions.md),
shared with GitHub Copilot. Follow them. The documentation site is in `docs/`
(Astro Starlight) and is published at <https://laravel-starter.entapp.northwestern.edu/>.

## Stack

- PHP 8.5, Laravel 13, PostgreSQL, Redis queues.
- Filament 5 on Livewire 4, Alpine and Tailwind CSS 4, branded by
  `northwestern-sysdev/northwestern-filament-theme`. This is the only frontend stack: no
  Bootstrap, Font Awesome, jQuery or other component libraries.
- Northwestern integrations from `northwestern-sysdev/laravel-soa` and
  `northwestern-sysdev/chassis` (WebSSO, Entra ID, Directory Search, EventHub, API tokens).
- Pest and PHPUnit for PHP tests; Pest's browser plugin and Playwright for browser tests, which
  check every page with axe through Chassis's browser expectations.

## Commands

| Task                            | Command                                                                        |
| ------------------------------- | ------------------------------------------------------------------------------ |
| Run the app locally             | `composer dev` (server, queue, logs, Vite)                                     |
| Sign in locally                 | Open `/app/login/as/nuit.admin` (or another `DemoUserSeeder::SIGN_IN_AS` user) |
| PHP tests                       | `vendor/bin/pest --parallel`                                                   |
| One test file                   | `vendor/bin/pest tests/Feature/Path/To/SomeTest.php`                           |
| Static analysis                 | `composer analyse:php` (PHPStan, must report no errors)                        |
| Format PHP                      | `composer format:php` (Pint)                                                   |
| Format Blade, CSS, TS, Markdown | `pnpm format` (Prettier)                                                       |
| Type-check TypeScript           | `pnpm typecheck`                                                               |
| Build assets                    | `pnpm build`                                                                   |
| Browser tests                   | `composer test:browser` (after `pnpm build`; not part of `pest --parallel`)    |

Before calling work done, run the PHP tests, PHPStan and both formatters, rebuild assets if
you changed Blade, CSS or TypeScript, and run the browser tests if you added or changed a page.
CI runs all of them, and its lint job commits formatting fixes back to the pull request, so pull
before pushing again.

## Where code goes

- **Domain code** in `app/Domains/{Domain}/`: `Actions/`, `Models/`, `Enums/`, `Jobs/`,
  `Policies/` and so on. `Core` holds shared building blocks (`BaseModel`, model concerns,
  casts, health checks).
- **The app panel** (`/app`, the default panel) in `app/Filament/App/`. Applications build
  their features here. Its sidebar is for application features; site-wide links live in the
  Help menu (`<x-help-menu>`).
- **The MCP server** (`/mcp`, off unless `MCP_ENABLED`) in `app/Mcp/`: `Servers/AppServer.php`
  and its tools in `Tools/`, with routes in `routes/ai.php`. Generate a tool with
  `php artisan make:mcp-tool`, list it in `AppServer`, and gate it on the person's permissions in
  `shouldRegister()`.
- **The administration panel** (`/administration`) in `app/Filament/` outside `App/`, for
  back-office tools. Filament generators target the app panel unless you pass
  `--panel=administration`.
- **Public pages** in `resources/views/public/`, on `<x-layouts.public>`, with routes in the
  `panel:app` middleware group in `routes/web.php`.
- **Error pages** in `resources/views/errors/`. Client errors (401, 402, 403, 404, 419, 429)
  render on the public layout; 500, 503 and database-paused use `<x-layouts.error>`, which
  must not use Filament, auth or the database.
- **Shared header**: `<x-site-header>` on public, sign-in, lockdown and error pages;
  `<x-panel-brand>` adds the application name to the panels' top bar.
- **Configuration** in `config/`, read from env variables with sane defaults. Starter
  settings live mostly in `config/platform.php` (retention, lockdown, stakeholders),
  `config/support.php` and `config/northwestern-filament-theme.php` (unit details, footer).

## Rules that are easy to miss

- **Tailwind skips what git ignores.** The app and administration themes and
  `resources/css/errors.css` detect sources automatically, so a class in any file the
  repository tracks compiles. A class that appears only in a package's views under `vendor/`
  needs an `@source` line, as `vendor/filament/**` has, or it silently does nothing. A new
  panel's theme needs that line too. Error-layout pages (500, 503, database-paused) use
  `resources/css/errors.css`, not a panel theme.
- **Filament callout headings are always `<h4>`.** A callout directly under a page title or
  a top-level section skips heading levels and fails axe. Use `Callout::make()` with a
  description that opens with bold text instead of a heading.
- **Clickable table rows wrap every cell in a link.** A cell that can be empty needs a
  `->placeholder()`, or it becomes a link with no text.
- **Panel tests:** `app` is the default panel. Livewire tests of administration pages must
  call `Filament::setCurrentPanel(AdministrationPanelProvider::ID)` first.
- **Render hooks registered when a panel boots persist for the rest of a test.** Request
  `/app` and `/administration` in separate tests when asserting on panel chrome.
- **Never add `$fillable` or `$guarded`** to models, never add foreign key constraints, and
  never implement a migration's `down()` (throw `NoRollbackException`). The only foreign keys
  are in migrations published by packages and kept as published: Spatie's permission tables,
  Telescope's, and Filament's `imports`, `exports` and `failed_import_rows`.
- **Passport's migrations stay as Passport publishes them** (`create_oauth_*`): UUID client
  IDs, string token IDs, `foreignUuid()` and a `down()`. Columns elsewhere that refer to them
  (`oauth_client_id`, `token_id`) use Passport's types. Add the starter's own OAuth columns in a
  separate migration, as `add_starter_columns_to_oauth_clients_table` does.
- **Edit an unreleased migration instead of adding another one.** Check `git tag --contains`
  before deciding a migration has shipped.
- **Retention:** records that should expire use Chassis's `PrunesAfterRetentionPeriod` trait
  (`Northwestern\SysDev\Chassis\Models\Concerns`) and a key under `platform.retention`; the
  daily `model:prune` deletes them. Don't cast those env values to `(int)`: `null` must stay
  null (keep forever), not become 0.
- **Mail templates:** don't let Prettier reformat `resources/views/vendor/mail/` or
  `resources/views/mail/` (both are in `.prettierignore`). Indentation inside Markdown mail
  becomes code blocks.
- **Secrets** belong in `.env`, never in committed files. Add new settings to `.env.example`
  with a safe default.

## Tests

- Mirror the namespace under `tests/Feature` or `tests/Unit`, and put a regression test in the
  existing test file for that class. Mark test classes with `#[CoversClass]`, or
  `#[CoversTrait]` for traits.
- **Coverage must stay at 100%, and only declared targets count.** A test's lines count only
  for the classes and traits its attributes name, so a new trait needs a `#[CoversTrait]`
  somewhere. A test that exercises included code must not also name a class the `<source>`
  exclusions in `phpunit.xml` leave out (models, enums, `app/Filament` and others): PHPUnit
  warns and drops everything that test covers, so the included classes show as untested.
  Check with
  `herd coverage -dmemory_limit=2G vendor/bin/pest --coverage --min=100`.
- Use factories. `UserFactory` gives SSO users the Northwestern User role; use `->affiliate()`
  for a user without roles.
- **Browser tests** in `tests/Browser` (Pest functions on `Tests\BrowserTestCase`) check every
  page the starter ships with `toBeHealthy()`: axe, browser errors, server errors and broken
  images, in light and dark mode. `FilamentPages::in()` finds each panel's pages, so a new
  Filament page is checked automatically; add record pages, public pages and pages that need
  data to the files in `tests/Browser/Pages`. `toBeHealthy(exclude: ['selector'])` excludes an
  element when a third-party widget can't be fixed, with a comment saying why.
- In browser tests, `@name` selects by `data-testid`, and a selector with no CSS punctuation is
  matched as text. Never commit `->debug()` or `->tinker()`: CI's lint job fails on them.
