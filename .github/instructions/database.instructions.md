---
applyTo: "database/migrations/**/*.php,database/seeders/**/*.php,database/factories/**/*.php,database/**/*.sql"
---

# Database & Persistence Instructions

## Schema & naming conventions

- **Tables**: plural, `snake_case` names (`api_tokens`, `login_records`). Pivot tables follow `singular_singular` alphabetical order (`role_user`, not `user_role`).
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

**Example migration pattern:**

```php
public function up(): void
{
    Schema::create('api_tokens', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->index();

        $table->string('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();

        $table->timestamps();
        $table->softDeletes();
    });
}

public function down(): void
{
    throw new NoRollback();
}
```

---

## Seeders & factories

### Factory patterns

- **Foreign key relationships:** Columns ending with `_id` should call the related model's `factory()` method instead of using Faker:
    ```php
    'user_id' => User::factory(),  // ✓ Correct
    'user_id' => fake()->randomDigit(),  // ✗ Wrong
    ```
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

- Seeders extend `App\Domains\Core\Seeders\IdempotentSeeder` (not `Illuminate\Database\Seeder`)
- Use `#[AutoSeed]` attribute to define seeder dependencies
- The `php artisan db:rebuild` command automatically discovers and runs seeders in dependency order

**Seeder organization:**

- Place seeders in `database/seeders/Domains/{DomainName}/`
- Use domain-driven organization matching your app structure
- Document dependencies clearly with `#[AutoSeed]` attribute

---

## Query optimization

- **Eager loading:** Always eager load relationships to avoid N+1 queries:

    ```php
    $users = User::with(['roles', 'api_tokens'])->get();  // ✓ Correct

    foreach ($users as $user) {
        $user->roles;  // Already loaded
    }
    ```

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

- **Select specific columns:** Only select columns you need:
    ```php
    User::select(['id', 'name', 'email'])->get();
    ```
- **Chunk large datasets:** For processing large result sets, use `chunk()` or `lazy()`:
    ```php
    User::query()->chunk(100, function ($users) {
        foreach ($users as $user) {
            // Process
        }
    });
    ```

---

## Tooling & operations

- **Migration validation:** Run `php artisan migrate --pretend` locally for destructive changes to validate generated SQL before committing.
- **Database rebuilding:** Use `php artisan db:rebuild` during development to reset database with all migrations and seeders.
- **Seeder validation:** Run `php artisan db:seed:list` to verify seeder dependency order and detect circular dependencies.
- **Model inspection:** Use `php artisan model:show User` to inspect model attributes, relationships, and events.
