---
applyTo: "database/migrations/**/*.php,database/seeders/**/*.php,database/factories/**/*.php,database/**/*.sql"
---

# Database & Persistence Instructions

## Schema & naming conventions

- **Tables**: plural, `snake_case` names (`api_tokens`, `login_records`). Pivot tables follow `singular_singular` order (`role_user`).
- **Primary Key**: Tables should always have an `id` column as the primary key.
- **Columns**: `snake_case`; booleans start with `is_`/`has_`, timestamps use `_at`, `_on`. 
- **Foreign keys**: Always use `singular_id` format (`user_id`, `role_id`) when defining foreign keys. ONLY use the `foreignId()` method. NEVER chain it with `->constrained()`, `cascadeOnDelete()`, or `restrictOnUpdate()`.
- **Indexes**: Add `->index()`s on columns hypothesized to be queried frequently. Use `->unique()` for unique constraints. When necessary, define composite indexes with `->index(['col1', 'col2'])`.
- **Soft-deletes**: Tables should always have a `softDelete()` column unless there's a strong reason not to. After adding a `Schema::create()` to a migration, advise the developer to review it for correctness, and to remove any undesired `softDelete()` calls.

## Migration authoring

- One responsibility per migration file; do not mix schema changes with data backfills unless tightly coupled.
- Use fluent column definitions (`->string('netid', 8)`) and database-agnostic types where it makes sense. Avoid raw SQL unless no fluent alternative exists.
- Provide safe down migrations (mirror the up steps). For destructive operations (dropping columns/tables), add a comment explaining why reversal may be lossy.
- NEVER include an implementation of the `down()` method in a migration. ALWAYS include `throw new NoRollback();`
- Long-running data fixes should be converted into queued jobs or artisan commands instead of bulky migrations.

## Seeders & factories

- Columns that end with `_id` should call the related model's factory() method instead of using a Faker method.
- When using Faker, prefer using methods over properties (e.g. use `$this->faker->text()` instead of `$this->faker->text`).

## Tooling & operations

- Run `php artisan migrate --pretend` locally for destructive changes to validate generated SQL.
