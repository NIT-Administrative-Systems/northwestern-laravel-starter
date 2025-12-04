---
applyTo: "app/**/*.php,bootstrap/**/*.php,config/**/*.php,routes/**/*.php,tests/**/*.php"
---

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
- **Policies & authorization**: Register policies per model and check them explicitly. Align permissions with `spatie/laravel-permission` using enum-based permission constants (e.g., `PermissionEnum`). Roles are categorized via `SystemRoleEnum` for system-managed roles vs application-managed roles.
- **Livewire & Filament**: Keep Livewire components lean, delegating heavy logic to actions. In Filament resources, extract form/table definitions into methods for reuse and keep validation centralized.

## Testing & quality gates

- **Test organization:** Write feature/unit tests using PHPUnit; colocate tests under `tests/Feature` or `tests/Unit` mirroring namespaces. Use `#[CoversClass(ClassName::class)]` attributes on test classes for coverage tracking.
- **Factory usage:** Prefer factories over manual model creation. Note that `UserFactory` automatically assigns `SystemRoleEnum::NORTHWESTERN_USER` to SSO users via `afterCreating` hook; use `->affiliate()` state for users without auto-assigned roles.
- **Mocking external services:** Mock external services (e.g., `ImpersonateManager`, `DirectorySearchService`) in tests rather than relying on real API calls or session state. Use `Event::fake()` and `Queue::fake()` to test event/job dispatching without side effects.
- **Static analysis:** Run `composer analyse:php` (PHPStan) before merging. Keep baseline errors at zero—add `@phpstan-ignore-next-line` only with justification.
- **Code formatting:** Always run `composer format:php` (Laravel Pint) before committing to ensure PSR-12 compliance.
