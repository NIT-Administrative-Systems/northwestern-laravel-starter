# PHP & Laravel Backend Instructions

## Coding standards & style

- **Formatting**: Follow PSR-12 exactly; run `composer format:php` before committing. Keep imports alphabetized and grouped.
- **Strict typing**: Start files with `declare(strict_types=1)`;. Use typed properties, scalar/union/DTO types, and return types everywhere. Favor constructor property promotion and `readonly` when stable immutability is intentional.
- **PHP language features**: Use named arguments, match expressions, nullsafe operator (?->), and attributes over DocBlock annotations. Prefer enums for constrained sets and value objects for domain primitives.
- **Naming**: Classes/interfaces/traits in `PascalCase`, methods/variables in `camelCase`, configuration keys in `snake_case`, enum cases and class constants in `UPPER_SNAKE_CASE`.
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
- **Enums**: Use backed string enums for database values (e.g., `AuthTypeEnum`, `SystemRoleEnum`, `PermissionEnum`). Document each case with PHPDoc.
- **Eloquent**: Favor query scopes, custom casts, and value objects over raw queries. **Never add `$fillable` or `$guarded` to Eloquent models** - omit them entirely for mass assignment protection. Always eager load relationships needed to avoid N+1 queries. Use custom query builders extending `Illuminate\Database\Eloquent\Builder` with proper type hints.
- **Model inheritance**: Models should extend `App\Domains\Core\Models\BaseModel` which provides automatic audit logging. Exception: `User` extends `Authenticatable` but uses the `Auditable` concern directly.
- **Model properties**: Use `protected $hidden` array for sensitive fields (passwords, tokens). Use `protected $casts` property for type casting, NOT the `casts()` method. Define `protected array $auditExclude` to exclude fields from audit logs (e.g., timestamps that change frequently, tokens, passwords).
- **Model attributes**: Use Laravel's `Attribute` casting for computed properties. Mark with `@comment` for code generator support. Pattern: `protected function attributeName(): Attribute { return Attribute::make(get: fn() => ...) }`.
- **Model concerns**: Extract reusable model behavior into traits in `Models/Concerns/`. Examples: `Auditable`, `HandlesImpersonation`, `AuditsRoles`.
- **Policies & authorization**: Register policies per model and check them explicitly. Align permissions with `spatie/laravel-permission` using enum-based permission constants (e.g., `PermissionEnum`).
- **Livewire & Filament**: Keep Livewire components lean, delegating heavy logic to actions. In Filament resources, extract form/table definitions into methods for reuse and keep validation centralized.

## Testing & quality gates

- **Test organization:** Write feature/unit tests using PHPUnit; colocate tests under `tests/Feature` or `tests/Unit` mirroring namespaces. Use `#[CoversClass(ClassName::class)]` attributes on test classes for coverage tracking.
- **Factory usage:** Prefer factories over manual model creation. Note that `UserFactory` automatically assigns `SystemRoleEnum::NORTHWESTERN_USER` to SSO users via `afterCreating` hook; use `->affiliate()` state for users without auto-assigned roles.
- **Mocking external services:** Mock external services (e.g., `ImpersonateManager`, `DirectorySearchService`) in tests rather than relying on real API calls or session state. Use `Event::fake()` and `Queue::fake()` to test event/job dispatching without side effects.
- **Static analysis:** Run `composer analyse:php` (PHPStan) before merging. Keep baseline errors at zero—add `@phpstan-ignore-next-line` only with justification.
- **Code formatting:** Always run `composer format:php` (Laravel Pint) before committing to ensure PSR-12 compliance.

# Database & Persistence Instructions

## Schema & naming conventions

- **Tables**: plural, `snake_case` names (`access_tokens`, `login_records`). Pivot tables follow `singular_singular` alphabetical order (`role_user`, not `user_role`).
- **Primary Key**: Tables should always have an `id` column as the primary key (`$table->id()`).
- **Columns**: `snake_case`; booleans start with `is_`/`has_`, timestamps use `_at` suffix, dates use `_on` suffix.
- **Foreign keys**: Always use `singular_id` format (`user_id`, `role_id`) when defining foreign keys. ONLY use the `foreignId()` method. NEVER chain it with `->constrained()`, `->cascadeOnDelete()`, or `->restrictOnUpdate()` - this project intentionally avoids database-level constraints.
- **Indexes**: Add `->index()` on columns hypothesized to be frequently queried in WHERE clauses or JOIN conditions. Use `->unique()` for unique constraints. Define composite indexes with `->index(['col1', 'col2'])` when querying multiple columns together.
- **Soft-deletes**: Tables should have `$table->softDeletes()` unless there's a strong reason not to (e.g., log/audit tables, pivot tables). After adding a `Schema::create()` to a migration, review for correctness and remove any undesired `softDeletes()` calls.

## Migration authoring

- **One responsibility per file:** Do not mix schema changes with data backfills unless tightly coupled.
- **Database-agnostic types:** Use fluent column definitions (`->string('netid', 8)`, `->text('notes')`) and avoid raw SQL unless no fluent alternative exists.
- **No rollback implementations:** NEVER include an implementation of the `down()` method. ALWAYS use `throw new NoRollback();`.
- **Long-running operations:** Data migrations affecting large tables should be converted into queued jobs or artisan commands instead of bulky migrations. Migrations should only define schema changes.
- **Column order:** Group columns logically - primary key first, foreign keys together, timestamps last. This improves readability and maintainability.

---

## Seeders & factories

### Factory patterns

- **Foreign key relationships:** Columns ending with `_id` should call the related model's `factory()` method instead of using Faker.
- **Faker methods:** Always use Faker methods (not properties):
    ```php
    'description' => fake()->text(),  // ✓ Correct
    'description' => fake()->text,  // ✗ Wrong
    ```
- **Factory states:** Use states for variations of the same model:
    ```php
    User::factory()->affiliate()->create();
    User::factory()->api()->create();
    ```

### Idempotent seeding

This starter uses a custom **idempotent seeding pattern** that allows seeders to run multiple times safely without duplicating data.

**Key components:**

- For seeders that _should_ be idempotent, extend `App\Domains\Core\Seeders\IdempotentSeeder` and not `Illuminate\Database\Seeder`. These seeds should include the `#[AutoSeed]` attribute and define dependencies, if any.

---

## Query optimization

- **Eager loading:** Always eager load relationships to avoid N+1 queries using `with()`, `load()` or `loadMissing()`:
- **Query scopes:** Define reusable query logic as scopes on models or custom query builders:

    ```php
    // In UserBuilder.php
    public function sso(): self
    {
        return $this->where('auth_type', AuthTypeEnum::SSO);
    }

    // Usage
    User::query()->sso()->get();
    ```

- **Select specific columns:** Only select columns you need if applicable to avoid hydrating full models unnecessarily.
- **Chunk large datasets:** For processing large result sets, use `chunk()` or `lazy()`.

---

# Frontend & UI Instructions

## Dual UI Architecture

This application uses **two separate and distinct UI stacks** optimized for different audiences. Understanding which stack to use is critical.

### Northwestern Laravel UI (User-Facing)

**When to use:**

- Public-facing pages and landing pages
- Custom user dashboards and workflows
- Any interface requiring Northwestern branding
- Complex custom experiences needing full design control

**Stack details:**

- **Layout:** Extend `northwestern::purple-container` or other Northwestern layouts
- **Styling:** Bootstrap 5 (utility classes like `btn`, `card`, `row`, `col-*`)
- **Icons:** Font Awesome (`<i class="fas fa-icon">`)
- **Components:** Custom Blade components in `resources/views/components/`

### Filament (Administration Interface)

**When to use:**

- Staff/developer admin panels (`/administration`)
- CRUD operations and data management
- Analytics dashboards and reporting
- User/system configuration
- Internal tooling

**Stack details:**

- **Framework:** Filament resources, pages, and widgets
- **Styling:** Tailwind CSS utility classes
- **Icons:** Heroicons (`use Filament\Support\Icons\Heroicon;`)
- **Location:** `app/Filament/` directory

**Key patterns:**

```php
// Generating resources with correct model namespace
php artisan make:filament-resource User --generate --model-namespace=App\\Domains\\User\\Models

// Relation managers must specify full model path
php artisan make:filament-relation-manager --related-model=App\\Domains\\User\\Models\\User --attach Role users username
```

**Authorization:** Panel access is controlled via `User::canAccessPanel()` method checking permissions against panel IDs defined as constants (e.g., `AdministrationPanelProvider::ID`).

---

## Template structure & best practices

- **Keep Blade declarative:** Push business logic into view models, presenters, or Livewire components. Use `@php` blocks sparingly and never for business rules.
- **Component reusability:** Prefer Blade components (`<x-component>`) or includes for repeated UI fragments. Register view composers for globally shared data (navigation, user context).
- **Layout hierarchy:** Respect Laravel's layout stack by extending base layouts, defining `@section` blocks explicitly, and using `@push('scripts')`/`@stack` for per-page assets.
- **Security helpers:** Always use built-in helpers (`@can`, `@csrf`, `@vite`, `@method`) instead of manual HTML to maintain consistency and security.
- **Avoid inline PHP:** Never embed business logic in views. Views should only handle presentation logic (loops, conditionals for display).

---

## Styling & assets

- **Formatting:** Run `pnpm format` to lint CSS/SCSS via Prettier before committing.
- **Northwestern UI:** Use Bootstrap 5 utility classes for user-facing interfaces. Favor utility classes over custom CSS when possible.
- **Filament:** Use Tailwind CSS utilities within Filament resources. Do not mix Bootstrap classes in Filament views.

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
- **Progressive enhancement:** Render critical content server-side, then layer interactive behaviors (dropdowns, modals, tabs) through Livewire, Alpine, or Bootstrap data attributes.
