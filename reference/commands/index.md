# Artisan Commands

The starter includes several custom Artisan commands for managing your application’s database, configuration, and Northwestern integrations.

## Database Commands

### `db:rebuild`

Completely rebuild the database by dropping all tables, running migrations, and executing all auto-discovered seeders.

```bash
php artisan db:rebuild
```

**What it does:**

1. Clears application cache, queue, and schedule cache
2. Runs `migrate:fresh` (drops all tables and re-runs migrations)
3. Executes all seeders via `db:seed`
4. Generates the OAuth signing keys with `passport:keys` if `storage/oauth-private.key` doesn’t exist
5. Seeds demo data via `DemoSeeder`
6. Regenerates IDE model helper annotations with `ide-helper:models -N`

Afterwards it warns if jobs are waiting in the queue, and, locally with the API on, prints the demo API user’s service client ID and secret to try the API with.

> **Destructive Operation**
>
> This command will **completely wipe your database**. Only use in local and testing environments. Never run this in production.

**When to use:**

* Initial project setup
* After pulling schema changes from git
* When switching between feature branches with different schemas
* Resetting test data during development

**Related:** `db:wipe`, `migrate:fresh`, `db:seed`

***

### `db:seed:list`

Display all auto-discovered seeders with their dependencies and execution order.

```bash
php artisan db:seed:list
```

Options

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-show-dependencies)`--show-dependencies``flag`

Show full dependency tree

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-mermaid)`--mermaid``flag`

Output as Mermaid diagram

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-json)`--json``flag`

Output as JSON

**Output example:**

```plaintext
 ┌───┬──────────────────┬──────────────────────────────────┐
 │ # │ Seeder           │ Dependencies                     │
 ├───┼──────────────────┼──────────────────────────────────┤
 │ 1 │ PermissionSeeder │ none                             │
 │ 2 │ RoleTypeSeeder   │ none                             │
 │ 3 │ RoleSeeder       │ RoleTypeSeeder, PermissionSeeder │
 └───┴──────────────────┴──────────────────────────────────┘
```

**When to use:**

* Verify seeder discovery and dependencies
* Troubleshoot seeding order issues
* Document available seeders for your team

**See also:** [Idempotent Seeding](https://laravel-starter.entapp.northwestern.edu/architecture/idempotent-seeding/) documentation

***

### `db:snapshot:create {filename?}`

Create a snapshot of the current database state.

```bash
php artisan db:snapshot:create my-snapshot-name
```

Options

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-filename)`filename``argument`

Descriptive name for the snapshot. Defaults to `database-dump` if omitted.

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-skip-schema-validation)`--skip-schema-validation``flag`

Skip schema validation checks

**What it does:**

1. Validates database schema matches current migrations (unless skipped)
2. Dumps database to `database/snapshots/{name}.sql`
3. Stores metadata about the snapshot (checksum, timestamp, migration/seeder counts)

> **Tip**
>
> Use descriptive snapshot names like `clean-test-data` or `before-feature-x` to make them easier to identify later.

**When to use:**

* Before making risky database changes
* Saving a known-good state for testing
* Creating test data fixtures
* Preserving demo data

**See also:** [Database Snapshots](https://laravel-starter.entapp.northwestern.edu/features/database-snapshots/) documentation

***

### `db:snapshot:restore {filename?}`

Restore a previously created database snapshot.

```bash
php artisan db:snapshot:restore my-snapshot-name
```

Options

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-filename-2)`filename``argument`

Name of the snapshot to restore. Defaults to `database-dump` if omitted.

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-skip-schema-validation-2)`--skip-schema-validation``flag`

Skip schema validation checks

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-backup)`--backup``flag`

Create a backup of the current database before restoring

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-force)`--force``flag`

Skip confirmation prompt

**What it does:**

1. Validates snapshot exists
2. Checks schema compatibility (unless skipped)
3. Creates a backup if requested
4. Drops all current database tables
5. Restores data from snapshot SQL file

> **Destructive Operation**
>
> Restoring a snapshot will **completely replace** your current database. All current data will be lost.

**When to use:**

* Resetting to a known state during testing
* Recovering from bad data changes during development
* Switching between test scenarios

***

### `db:snapshot:list`

List all available database snapshots, then offer to restore one. Restoring from here skips the schema check that `db:snapshot:restore` runs.

```bash
php artisan db:snapshot:list
```

***

### `db:snapshot:info {filename?}`

Display detailed information about a specific snapshot.

```bash
php artisan db:snapshot:info my-snapshot-name
```

Options

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-filename-3)`filename``argument`

Name of the snapshot to inspect. If omitted, an interactive selector is shown.

**What it does:**

1. Displays file information (path, size, modification date)
2. Shows schema metadata (checksum, migration/seeder counts)
3. Compares snapshot schema with current codebase

**When to use:**

* Verifying a snapshot’s contents before restoring
* Checking if a snapshot is compatible with current code
* Reviewing snapshot metadata

***

### `db:snapshot:delete {filename?}`

Delete a database snapshot and its associated metadata.

```bash
php artisan db:snapshot:delete my-snapshot-name
```

Options

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-filename-4)`filename``argument`

Name of the snapshot to delete. If omitted, an interactive selector is shown.

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-all)`--all``flag`

Delete all snapshots

**What it does:**

1. Removes the SQL snapshot file from `database/snapshots/`
2. Cleans up associated metadata from the checksum map

**When to use:**

* Removing old or unused snapshots
* Freeing up disk space
* Cleaning up after testing

***

### `db:wake`

Wake up a potentially-inactive serverless RDS database by establishing a connection.

```bash
php artisan db:wake
```

Options

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-max-attempts)`--max-attempts``option``5`

Maximum number of connection attempts (1-20)

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-delay)`--delay``option``5`

Seconds to wait between attempts (1-30)

**What it does:**

Attempts to connect to the database and execute a simple query, causing serverless databases (like AWS Aurora Serverless) to wake from sleep mode.

**When to use:**

* Before running migrations
* As part of deployment scripts
* When you know the database has been idle

> **Note**
>
> This is primarily useful for AWS Aurora Serverless or similar serverless database configurations that automatically pause after periods of inactivity.

***

## Configuration Commands

### `config:validate`

Validate application configuration and environment variables.

```bash
php artisan config:validate
```

**What it does:**

Runs all auto-discovered config validators and reports their status. Built-in validators check:

* Application key
* SSO credentials (Entra ID or Online Passport)
* Directory Search API key
* Database connectivity
* Queue connection
* S3 storage access
* EventHub credentials (when not mocked)

Validators that aren’t relevant to the current configuration (e.g., EventHub when mocked) are automatically skipped and shown separately in the output.

**When to use:**

* After initial installation
* After environment variable changes
* Before deployment
* Troubleshooting integration issues

### Adding Custom Validators

Validators are discovered automatically using chassis’s `#[ValidatesConfig]` attribute, following the same pattern as `#[AutoSeed]` for seeders.

**1. Create a validator class** implementing `ConfigValidator` in a `Services/ConfigValidation` directory within your domain:

```php
<?php


namespace App\Domains\Billing\Services\ConfigValidation;


use Northwestern\SysDev\Chassis\Attributes\ValidatesConfig;
use Northwestern\SysDev\Chassis\Contracts\ConfigValidator;


#[ValidatesConfig(description: 'Stripe')]
class StripeValidator implements ConfigValidator
{
    public function shouldRun(): bool
    {
        // Return false to skip when the integration isn't configured
        return filled(config('services.stripe.secret'));
    }


    public function validate(): bool
    {
        return filled(config('services.stripe.secret'))
            && filled(config('services.stripe.key'));
    }


    public function successMessage(): string
    {
        return 'Stripe API keys are configured';
    }


    public function errorMessage(): string
    {
        return 'Stripe configuration is incomplete';
    }


    public function hints(): array
    {
        return [
            'Set <comment>STRIPE_KEY</comment> and <comment>STRIPE_SECRET</comment> in your .env file',
        ];
    }
}
```

**2. That’s it.** The validator is automatically discovered from any `app/Domains/*/Services/ConfigValidation` directory. No registration or configuration needed.

> **Tip**
>
> The `description` parameter on `#[ValidatesConfig]` controls the label shown in the command output. The `shouldRun()` method determines whether the validator is relevant. Return `false` for optional integrations that aren’t configured to avoid false negatives.

***

## User and Role Commands

### `role:force-detach {user} {role}`

Remove a role from a user in an emergency, bypassing the role’s assignment lock.

```bash
php artisan role:force-detach jdoe "Super Administrator" --reason="Offboarding"
```

Options

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-user)`user``argument`

Username or ID of the user. If omitted, a search prompt is shown.

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-role)`role``argument`

Name of the role to detach. If omitted, a search prompt is shown.

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-reason)`--reason``option`

Reason for the detachment, recorded in the audit log. Prompted for if omitted; required with `--force`.

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-force-2)`--force``flag`

Skip the confirmation prompt

**What it does:**

1. Finds the user and role, and fails if the user doesn’t have the role
2. Shows the user, role, assignment lock and reason, and asks for confirmation
3. Detaches the role with an audit record (origin `System`) that includes the reason

***

## Maintenance Commands

### `oauth-clients:notify-secret-expiration`

Send reminder emails for service client secrets approaching their expiration date.

```bash
php artisan oauth-clients:notify-secret-expiration
```

**What it does:**

1. Queries for active service clients whose secrets expire soon
2. Checks which are due for notifications (30, 14, 7, 3, 1 days before)
3. Sends reminder emails to the API user’s contact address

**See also:** [API Documentation](https://laravel-starter.entapp.northwestern.edu/features/api/#secret-expiration-notifications)

***

### `personal-access-tokens:notify-expiration`

Email people about their personal access tokens that are about to expire.

```bash
php artisan personal-access-tokens:notify-expiration
```

It emails the owner of each active token expiring in 30, 14, 7, 3 or 1 days (`api.personal_access_tokens.expiration_notifications.intervals`), unless they turned the reminders off on their Preferences page, and at most once a day per token. It does nothing while `API_PERSONAL_ACCESS_TOKEN_EXPIRATION_NOTIFICATIONS_ENABLED` is false.

**See also:** [Personal Access Tokens](https://laravel-starter.entapp.northwestern.edu/features/api/#personal-access-tokens)

***

### `oauth:revoke-ineligible`

Revoke the API credentials their holders can no longer use.

```bash
php artisan oauth:revoke-ineligible
```

It revokes each live personal access token, connection and service client that `CredentialAccess` refuses for a lasting reason: a deactivated or deleted account, or a person who lost `CreatePersonalAccessTokens` or `UseMcp`. A feature that is turned off isn’t one. The API already refuses these on every request; this makes the records say so, so the Account and Administration pages show them as revoked. Each goes through its action, so each is audited.

***

### `mcp:prune-clients`

Clean up the clients MCP clients register for themselves.

```bash
php artisan mcp:prune-clients
```

It deletes a self-registered client nobody connected within `mcp.cleanup.unconnected_hours` (24) of registering, and revokes one nobody has used for `mcp.cleanup.unused_days` (90). Administrator-registered applications and service clients are never touched.

**See also:** [MCP](https://laravel-starter.entapp.northwestern.edu/features/mcp/#managing-clients)

***

### `announcements:notify`

Notify the audiences of announcements that have started, when their authors asked for it.

```bash
php artisan announcements:notify
```

Publishing notifies at once for an announcement that starts straight away; this catches the ones scheduled to start later. Outside production it’s scheduled only at noon on weekdays, so run it by hand to send one sooner.

**See also:** [Announcements](https://laravel-starter.entapp.northwestern.edu/features/announcements/#notifying-people)

***

## Passport Commands

The starter schedules or calls these Laravel Passport commands.

### `passport:keys`

Generate the RSA key pair Passport signs access tokens with.

```bash
php artisan passport:keys
```

It writes `storage/oauth-private.key` and `storage/oauth-public.key`. `db:rebuild` runs it locally; deployed environments put the contents in `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` instead. Replacing the keys invalidates every outstanding access token.

Options

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-force-3)`--force``flag`

Overwrite keys that already exist

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-length)`--length``option`

Length of the private key (4096)

**See also:** [API Operations](https://laravel-starter.entapp.northwestern.edu/features/api-operations/#signing-keys)

***

### `passport:purge`

Delete OAuth tokens and authorization codes that are no longer needed.

```bash
php artisan passport:purge --expired --hours=744
```

The starter runs it daily with `--expired --hours=744`, so tokens and codes go once they expired more than 31 days ago, revoked or not. Without `--expired`, Passport also deletes everything revoked at once. Don’t run it that way: the starter finds a refresh token through its access token when access is revoked, so an access token has to outlive its refresh token’s 30-day lifetime.

***

## Health Check Commands

### `health:check`

Run health checks for all external integrations and services.

```bash
php artisan health:check
```

**What it does:**

Validates connectivity and configuration for:

* **Database** - Connection and query execution (production only)
* **Queue** - Fails when the heartbeat job hasn’t been processed within 15 minutes (production only)
* **Cache** - Cache store read/write
* **Schedule** - Scheduler heartbeat
* **Debug Mode** - Ensures debug mode is off (non-local)
* **Optimized App** - Ensures app is optimized (non-local)
* **Security Advisories** - Checks for known package vulnerabilities
* **Redis** - Redis connectivity (when Redis driver is in use)
* **Directory Search** - API connectivity and test query (skipped locally when `DIRECTORY_SEARCH_API_KEY` is blank)

**When to use:**

* After deployment
* Troubleshooting integration issues
* Monitoring service connectivity
* As part of CI/CD pipelines

**Integration:**

The `/api/health` endpoint also runs these checks on every request for automated monitoring. It refuses every request with `403` until `HEALTH_SECRET_TOKEN` is set:

```bash
curl -H "X-Secret-Token: your-secret-token" https://your-app.northwestern.edu/api/health
```

***

## Changelog Commands

### `make:changelog`

Scaffold a new changelog Markdown file in `resources/changelogs/`.

```bash
php artisan make:changelog
```

In interactive mode, the command prompts for a slug, date, and title with sensible defaults. Arguments and options can also be passed directly:

```bash
php artisan make:changelog 2026-03-01 --date=2026-03-01 --title="Search Improvements"
```

Options

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-slug)`slug``argument`

URL-safe identifier. Defaults to today’s date.

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-date)`--date``option`

Authored date in `YYYY-MM-DD` format. Defaults to today.

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-title)`--title``option`

Human-readable title. Defaults to “Month Year Release” when the slug is a date.

If a file with the same slug already exists, a numeric suffix is appended automatically.

**See also:** [Changelog](https://laravel-starter.entapp.northwestern.edu/features/changelog/) documentation

***

## Development Commands

### `netid:update:test {netId} {action}`

Simulate a message from the EventHub **NetID Updates** topic to test the NetID update webhook.

By default it posts the message straight to the webhook, so the `netid-update` route in `routes/api.php`, which ships commented out, must be enabled first. With `--via-queue` it sends the message through the real EventHub API instead, so it needs EventHub’s credentials.

```bash
php artisan netid:update:test jdoe deactivate
```

Options

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-netid)`netId``argument`

NetID to include in the message. Prompted for if omitted.

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-action)`action``argument`

Update action: `deactivate`, `deprovision` or `sechold`. A selector is shown if omitted.

[#](https://laravel-starter.entapp.northwestern.edu/reference/commands/#prop-via-queue)`--via-queue``flag`

Send through the EventHub queue instead of directly to the HTTP webhook

***

## IDE Helper Commands

The starter uses `laravel-ide-helper` to generate IDE autocomplete files. All three run from Composer’s `post-update-cmd` script and from `composer ide-helper:generate`. `db:rebuild` runs only `ide-helper:models -N`.

### `ide-helper:generate`

Generate IDE helper file for Laravel facades.

```bash
php artisan ide-helper:generate
```

### `ide-helper:models`

Generate PHPDoc annotations for Eloquent models.

```bash
php artisan ide-helper:models -N
```

`-N` (`--nowrite`) writes the annotations to `_ide_helper_models.php` instead of the model files.

### `ide-helper:meta`

Generate PhpStorm meta file for container bindings.

```bash
php artisan ide-helper:meta
```

> **Tip**
>
> `composer update` and `db:rebuild` keep these files current. You rarely need to run them manually.

***

## Scheduled Tasks

`routes/console.php` schedules these commands. Times are Chicago time (`schedule_timezone` in `config/app.php`). The scheduler must be running for any of them to run: deployments run `php artisan schedule:run` every minute, and locally `composer dev` doesn’t start it, so run `php artisan schedule:work`, or run a command by hand.

| Command                                            | Frequency                                                    | Purpose                                                                                                                                                                                                                       |
| -------------------------------------------------- | ------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `telescope:prune`                                  | Daily                                                        | Deletes Telescope entries older than 24 hours                                                                                                                                                                                 |
| `livewire:configure-s3-upload-cleanup`             | Daily                                                        | Sets the S3 lifecycle rule that expires Livewire’s temporary uploads after 24 hours (only when uploads use an S3 disk)                                                                                                        |
| `model:prune --path=app/Domains/*/Models`          | Daily                                                        | Prunes models in `app/Domains/*/Models` that use `Prunable` or `MassPrunable`, including those with `PrunesAfterRetentionPeriod`                                                                                              |
| `model:prune --model=HealthCheckResultHistoryItem` | Daily                                                        | Deletes health check results older than `keep_history_for_days` (5)                                                                                                                                                           |
| `oauth-clients:notify-secret-expiration`           | Daily at 09:00                                               | Sends client secret expiration reminders, when `api.client_secret_expiration_notifications.enabled` is true                                                                                                                   |
| `personal-access-tokens:notify-expiration`         | Daily at 09:00                                               | Emails people before their personal access tokens expire, unless they turned it off in their preferences, when `api.personal_access_tokens.expiration_notifications.enabled` is true                                          |
| `announcements:notify`                             | Every five minutes in production, weekdays at noon elsewhere | Notifies the audiences of [announcements](https://laravel-starter.entapp.northwestern.edu/features/announcements/) that have started, when their authors asked; publishing notifies at once for one that starts straight away |
| `oauth:revoke-ineligible`                          | Hourly in production, weekdays at noon elsewhere             | Revokes the credentials their holders can no longer use: deleted and deactivated accounts, and people who lost `CreatePersonalAccessTokens` or `UseMcp`                                                                       |
| `mcp:prune-clients`                                | Daily                                                        | Deletes self-registered [MCP clients](https://laravel-starter.entapp.northwestern.edu/features/mcp/) nobody connected within a day, and revokes those nobody has used for 90 days                                             |
| `passport:purge --expired --hours=744`             | Daily                                                        | Deletes OAuth tokens and codes, revoked or not, once they expired more than 31 days ago: longer than the 30-day refresh token lifetime                                                                                        |
| `health:schedule-check-heartbeat`                  | Every minute                                                 | Records the heartbeat the Schedule health check reads, in every environment                                                                                                                                                   |
| `health:queue-check-heartbeat`                     | Every five minutes, production only                          | Dispatches the job the Queue health check waits for                                                                                                                                                                           |
| `health:check`                                     | Every minute, production only                                | Runs and stores the health checks                                                                                                                                                                                             |
