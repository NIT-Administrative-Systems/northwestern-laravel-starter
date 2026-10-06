# PHP & Laravel Backend Instructions

## Coding standards & style

- **Formatting**: Follow the Pint configuration in `pint.json` (the `laravel` preset plus custom rules); run `composer format:php` before committing. Keep imports alphabetized and grouped.
- **Strict typing**: Start files with `declare(strict_types=1)`;. Use typed properties, scalar/union/DTO types, and return types everywhere. Favor constructor property promotion and `readonly` when stable immutability is intentional.
- **PHP language features**: Use named arguments, match expressions, nullsafe operator (?->), and attributes over DocBlock annotations. Prefer enums for constrained sets and value objects for domain primitives.
- **Naming**: Classes/interfaces/traits/enum cases in `PascalCase`, methods/variables in `camelCase`, configuration keys in `snake_case`, and class constants in `UPPER_SNAKE_CASE`.
- **DocBlocks**: Only add DocBlocks when types need extra context (e.g., collections with generics, third-party payloads). Use `@comment` for attribute/accessor documentation that should be picked up by code generators. Document _why_ a decision is made, not obvious logic.
- **Dependencies**: Prefer dependency injection over manually resolving classes out of the container when possible. Use Laravel contracts when binding abstractions for easier testing.

## Architectural conventions

- **Domain-driven structure**: Organize code in `app/Domains/{DomainName}/` with subfolders: `Actions/`, `Models/`, `Events/`, `Listeners/`, `Jobs/`, `Enums/`, `QueryBuilders/`, `Data/`, `Policies/`, etc. Group related business logic by domain, not by technical layer.
- **Actions & services**: Create single-responsibility action classes for business operations; mark them `__invoke()` and keep them stateless. Services aggregate related actions or third-party integrations.
- **Requests & resources**: Use `FormRequest` subclasses for validation/authorization. Use API Resources or DTOs to shape outbound payloads; never serialize Eloquent models directly in controllers.
- **Events, jobs, and listeners**: Push slow work (imports, notifications, audit syncing) onto queued jobs. Emit domain events to decouple side-effects. Jobs with retry logic should implement a `failed(Throwable $exception): void` method for handling exhausted retries.
- **Configuration safety**: Centralize feature toggles and integration credentials in `config/` files and env variables. Provide sane defaults and guard missing envs with helpful exceptions. Use `match` expressions for environment-specific defaults.

## Framework-specific guidance

- **Routing**: Use attribute or route-group organization with explicit middleware stacks. Keep route definitions thin; point to invokable controllers or action classes.
- **Enums**: Use backed string enums for database values (e.g., `AuthType`, `SystemRole`, `SystemPermission`). Document each case with PHPDoc.
- **Eloquent**: Favor query scopes, custom casts, and value objects over raw queries. **Never add `$fillable` or `$guarded` to Eloquent models** - omit them entirely for mass assignment protection. Always eager load relationships needed to avoid N+1 queries. Use custom query builders extending `Illuminate\Database\Eloquent\Builder` with proper type hints.
- **Model inheritance**: Models should extend `App\Domains\Core\Models\BaseModel` which provides automatic audit logging. Exception: `User` extends `Authenticatable` but uses the `Auditable` concern directly.
- **Model properties**: Use `protected $hidden` array for sensitive fields (passwords, tokens). Use `protected $casts` property for type casting, NOT the `casts()` method. Define `protected array $auditExclude` to exclude fields from audit logs (e.g., timestamps that change frequently, tokens, passwords).
- **Model attributes**: Use Laravel's `Attribute` casting for computed properties. Mark with `@comment` for code generator support. Pattern: `protected function attributeName(): Attribute { return Attribute::make(get: fn() => ...) }`.
- **Model concerns**: Extract reusable model behavior into traits in `Models/Concerns/`. Examples: `PrunesAfterRetentionPeriod`, `HandlesImpersonation`, `AuditsRoles`.
- **Policies & authorization**: Register policies per model and check them explicitly. Align permissions with `spatie/laravel-permission` using enum-based permission constants (e.g., `SystemPermission`).
- **Livewire & Filament**: Keep Livewire components lean, delegating heavy logic to actions. In Filament resources, extract form/table definitions into methods for reuse and keep validation centralized.

## Testing & quality gates

- **Test organization:** Write feature/unit tests using PHPUnit; colocate tests under `tests/Feature` or `tests/Unit` mirroring namespaces. Use `#[CoversClass(ClassName::class)]` attributes on test classes for coverage tracking, or `#[CoversTrait(TraitName::class)]` when testing a trait.
- **Factory usage:** Prefer factories over manual model creation. Note that `UserFactory` automatically assigns `SystemRole::NorthwesternUser` to SSO users via `afterCreating` hook; use `->affiliate()` state for users without auto-assigned roles.
- **Mocking external services:** Mock external services (e.g., `ImpersonateManager`, `Northwestern\SysDev\SOA\DirectorySearch`) in tests rather than relying on real API calls or session state. Use `Event::fake()` and `Queue::fake()` to test event/job dispatching without side effects.
- **Static analysis:** Run `composer analyse:php` (PHPStan) before merging. Keep baseline errors at zero—add `@phpstan-ignore-next-line` only with justification.
- **Code formatting:** Always run `composer format:php` (Laravel Pint, configured in `pint.json`) before committing.

# Database & Persistence Instructions

## Schema & naming conventions

- **Tables**: plural, `snake_case` names (`access_tokens`, `user_login_records`). Pivot tables follow `singular_singular` alphabetical order (`role_user`, not `user_role`).
- **Primary Key**: Tables should always have an `id` column as the primary key (`$table->id()`). Passport's `oauth_*` tables are the exception: their keys are UUIDs or token strings.
- **Columns**: `snake_case`; booleans name a state without an `is_`/`has_` prefix (`netid_inactive`, `system_managed`), timestamps use `_at` suffix, dates use `_on` suffix.
- **Foreign keys**: Always use `singular_id` format (`user_id`, `role_id`) when defining foreign keys. ONLY use the `foreignId()` method. NEVER chain it with `->constrained()`, `->cascadeOnDelete()`, or `->restrictOnUpdate()` - this project intentionally avoids database-level constraints. The only exceptions are Filament's `imports`, `exports` and `failed_import_rows` migrations, shipped in v1.10.0. Laravel Passport's `create_oauth_*` migrations are also kept as Passport publishes them: UUID client IDs, string token IDs, `foreignUuid()` and a `down()` method. Columns that refer to them (`oauth_client_id`, `token_id`) follow Passport's types, and the starter's own OAuth columns go in a separate migration.
- **Indexes**: Add `->index()` on columns hypothesized to be frequently queried in WHERE clauses or JOIN conditions. Use `->unique()` for unique constraints. Define composite indexes with `->index(['col1', 'col2'])` when querying multiple columns together.
- **Soft-deletes**: Tables should have `$table->softDeletes()` unless there's a strong reason not to (e.g., log/audit tables, pivot tables). After adding a `Schema::create()` to a migration, review for correctness and remove any undesired `softDeletes()` calls.

## Migration authoring

- **One responsibility per file:** Do not mix schema changes with data backfills unless tightly coupled.
- **Database-agnostic types:** Use fluent column definitions (`->string('netid', 8)`, `->text('notes')`) and avoid raw SQL unless no fluent alternative exists.
- **No rollback implementations:** NEVER include an implementation of the `down()` method. ALWAYS use `throw new NoRollbackException();`.
- **Long-running operations:** Data migrations affecting large tables should be converted into queued jobs or artisan commands instead of bulky migrations. Migrations should only define schema changes.
- **Column order:** Group columns logically - primary key first, foreign keys together, timestamps last. This improves readability and maintainability.

---

## Seeders & factories

### Factory patterns

- **Foreign key relationships:** Columns ending with `_id` should call the related model's `factory()` method instead of using Faker.
- **Faker methods:** Always use Faker methods (not properties): `fake()->text()` instead of `fake()->text`.
- **Factory states:** Use states for variations of the same model (`User::factory()->affiliate()`).

### Idempotent seeding

This starter uses a custom **idempotent seeding pattern** that allows seeders to run multiple times safely without duplicating data.

**Key components:**

- For seeders that _should_ be idempotent, extend `Northwestern\SysDev\Chassis\Seeding\IdempotentSeeder` and not `Illuminate\Database\Seeder`. These seeds should include the `Northwestern\SysDev\Chassis\Attributes\AutoSeed` attribute (`#[AutoSeed]`) and define dependencies, if any.

---

## Query optimization

- **Eager loading:** Always eager load relationships to avoid N+1 queries using `with()`, `load()` or `loadMissing()`.
- **Query scopes:** Define reusable query logic as scopes on models or custom query builders.
- **Select specific columns:** Only select columns you need if applicable to avoid hydrating full models unnecessarily.
- **Chunk large datasets:** For processing large result sets, use `chunk()` or `lazy()`.

---

# Frontend & UI Instructions

## UI Architecture

This application has **one frontend stack**: Filament (Livewire, Alpine and Tailwind CSS), with Northwestern branding from `northwestern-sysdev/northwestern-filament-theme`. There is no Bootstrap. Don't add Bootstrap classes, Font Awesome, jQuery or another component library.

Put each page where it belongs:

| Surface                                  | Location                         | Use for                                                                                                                                                                                                          |
| ---------------------------------------- | -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| App panel (`/app`, default panel)        | `app/Filament/App/`              | The application's features for its users. Default to this.                                                                                                                                                       |
| Administration panel (`/administration`) | `app/Filament/` (outside `App/`) | Back-office tooling                                                                                                                                                                                              |
| Public layout (`<x-layouts.public>`)     | `resources/views/public/`        | Pages that must work without signing in. Routes use the `panel:app` middleware so Filament's Blade components work. Light only.                                                                                  |
| Error layout (`<x-layouts.error>`)       | `resources/views/errors/`        | The 500, 503 and database-paused pages. No Filament, auth or database calls; wrap anything that could query the database in `rescue()`. Client error pages (401, 402, 403, 404, 419, 429) use the public layout. |

**Stack details:**

- **Framework:** Filament resources, pages, widgets and schema components; Filament Blade components (`<x-filament::button>` and so on) outside panels
- **Styling:** Tailwind CSS utility classes and the Northwestern tokens (`text-nu-purple-100`, `font-nu-heading`)
- **Icons:** Heroicons (`use Filament\Support\Icons\Heroicon;`)
- **Branding config:** `config/northwestern-filament-theme.php` (unit details, lockup, footer links)

**Key patterns:**

```php
// Generators target the default (app) panel; add --panel=administration for back-office resources
php artisan make:filament-resource User --generate --model-namespace=App\\Domains\\User\\Models

// Relation managers must specify full model path
php artisan make:filament-relation-manager --panel=administration --related-model=App\\Domains\\User\\Models\\User --attach Role users username
```

**Authorization:** Panel access is controlled via `User::canAccessPanel()`, which matches on panel IDs defined as constants (`AppPanelProvider::ID`, `AdministrationPanelProvider::ID`). Add a case for every new panel.

**Testing:** `app` is the default panel. Livewire tests of administration pages must call `Filament::setCurrentPanel(AdministrationPanelProvider::ID)` first.

---

## Template structure & best practices

- **Keep Blade declarative:** Push business logic into view models, presenters, or Livewire components. Use `@php` blocks sparingly and never for business rules.
- **Component reusability:** Prefer Blade components (`<x-component>`) or includes for repeated UI fragments. Register view composers for globally shared data (navigation, user context).
- **Layout hierarchy:** Outside panels, use the layout components (`<x-layouts.public>`, `<x-layouts.error>`). `@push('scripts')` works only on `<x-layouts.error>`; `<x-layouts.public>` has no `@stack('scripts')`, so give public pages behavior with Alpine, which the layout already loads.
- **Security helpers:** Always use built-in helpers (`@can`, `@csrf`, `@vite`, `@method`) instead of manual HTML to maintain consistency and security.
- **Avoid inline PHP:** Never embed business logic in views. Views should only handle presentation logic (loops, conditionals for display).

---

## Styling & assets

- **Formatting:** Run `pnpm format` to lint CSS via Prettier before committing.
- **Tailwind:** Use Tailwind utilities and the Northwestern tokens everywhere. Favor utility classes over custom CSS.
- **Tailwind sources:** Views outside `app/Filament/App/` and `resources/views/filament/app/` must be listed under `@source` in `resources/css/filament/app/theme.css`, or their classes won't be compiled. Error-layout pages (500, 503, database-paused) compile from `resources/css/errors.css`, which has its own `@source` lines.

---

## Livewire & interactivity

- **Component responsibility:** Livewire components focus on state management; delegate heavy processing to backend action classes.
- **Data exposure:** Only expose data needed by the view. Avoid passing entire models - use DTOs or select specific properties.
- **Validation:** Use `rules()` method or FormRequest classes for validation, never inline validation logic.
- **Actions integration:** Call action classes from Livewire methods instead of embedding business logic.

---

## Filament customization

- **Form builders:** Use Filament's form builder methods. Keep form definitions in dedicated methods for reusability.
- **Table configuration:** Define filters, actions, and bulk actions in the resource's `table()` method.
- **Navigation:** Use navigation groups and sort orders to organize panel structure. Define navigation via resource's `$navigationGroup` and `$navigationSort` properties.

---

## Interface copy

Write copy the way Northwestern does: plainly, in the second person, active voice, no jargon on pages everyone uses. The [Northwestern A to Z Style Guide](https://www.northwestern.edu/brand/editorial-guidelines/style-guide/) and the [Northwestern IT Style Guide](https://www.it.northwestern.edu/departments/it-services-support/it-communications/branding/style-guide.html) settle anything not covered here.

**Names are title case; sentences are sentence case.**

| Title case (names)                                                                                                                                                                                                          | Sentence case (sentences)                                                                                                                                                         |
| --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Page titles, navigation, tabs, section headings, wizard steps, buttons, menu items, modal headings, field labels, column headers, filters and their options, enum labels, badges, notification titles, empty-state headings | Descriptions, subheadings, helper text, placeholders, modal descriptions, notification bodies, empty-state descriptions, validation and error messages, email subjects and bodies |

- Title case follows Chicago headline style: articles, coordinating conjunctions and prepositions of any length stay lowercase unless first or last ("Applications with Access to Your Account"); a verb's particle and the second part of a hyphenated word are capitalized ("Sign In with Email", "Sign-In Records"). `App\Filament\Support\Formatting\TitleCase::of()` applies it.
- Keep model labels lowercase (`protected static ?string $modelLabel = 'service client';`) unless they start with a proper noun or acronym ("API request", "MCP client"). Filament puts them into sentences as they are, and title-cases them for page titles, navigation and the built-in actions configured in `FilamentServiceProvider`.
- Filament's own labels follow the same rule through `lang/vendor/filament-*/en/`, which overrides only the keys that differ. Check those keys when upgrading Filament.

**One name for one thing:**

- **Sign in**, **sign-in**, **sign out**: never "log in", "login" or "logout" in copy (identifiers in code are fine).
- **Personal access token** for the tokens people create in Account; "access token" alone only for OAuth tokens in general.
- **Service client** for an API user's client-credentials client; never a bare "client" in a label.
- **Application** for an OAuth application an administrator registers.
- **AI client** in copy everyone reads; **MCP client** only in Administration. A self-registered client's name is "not verified".
- **Connections** for the people connected to an application.
- **NetID**, **Northwestern Directory**, **Northwestern IT**, **IT Service Desk** (847-491-4357 (1-HELP)). Never "NU", "NUIT" or other informal abbreviations.
- **email** is lowercase mid-sentence; the field label is "Email".

**Northwestern style:**

- Times: "4 p.m.", "10:12 a.m.", "noon", "midnight", the time before the date, months spelled out, the year only when it isn't this year.
- Spell out one through nine in sentences; numerals are fine in tables, badges and pickers. Use real plurals, never "minute(s)".
- Use the serial comma; no ampersands in place of "and"; no exclamation points.

**Tone:** say what happened and what to do next. Leave out "Sorry", "Please" and "Unable to": "We couldn't resend the code. Try again in a minute." Keep every sentence true for any application built from the starter (say "the team that supports {app name}", not "Northwestern IT").

---

## Accessibility & performance

- **WCAG 2.1 AA compliance:** All views must meet accessibility standards:
    - Semantic heading hierarchy (`<h1>` → `<h2>` → `<h3>`)
    - All form controls must have associated `<label>` elements
    - Visible focus states on interactive elements
    - ARIA attributes only when semantic HTML is insufficient
    - Sufficient color contrast ratios (4.5:1 for normal text, 3:1 for large text)
- **Images:** Always include descriptive `alt` text. Use empty `alt=""` only for decorative images. Optimize images before committing.
- **Icons:** Mark decorative icons with `aria-hidden="true"`. Provide text alternatives for functional icons.
- **Asset optimization:**
    - Defer non-critical JavaScript using `@vite` with proper chunking
    - Avoid inlining large unminified bundles
    - Use lazy loading for images below the fold
    - Leverage browser caching via versioned assets
- **Progressive enhancement:** Render critical content server-side, then layer interactive behaviors (dropdowns, modals, tabs) through Livewire and Alpine.
