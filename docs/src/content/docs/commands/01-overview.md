---
title: Artisan Commands Overview
description: Complete reference of all custom Artisan commands
---

## Overview

The Northwestern Laravel Starter includes several custom Artisan commands to streamline development, database management, and system maintenance. These commands complement Laravel's built-in commands with Northwestern-specific and project-specific functionality.

## Command Categories

### Project Setup

- [`customize:project`](#customizeproject) - Interactive project customization wizard

### Database Management

- [`db:rebuild`](#dbrebuild) - Complete database rebuild workflow
- [`db:wake`](#dbwake) - Wake up paused/sleeping databases
- [`snapshot:create`](#snapshotcreate) - Create database snapshot
- [`snapshot:restore`](#snapshotrestore) - Restore from database snapshot
- [`snapshot:list`](#snapshotlist) - List all available snapshots

### Seeder Management

- [`seeders:list`](#seederslist) - List all auto-seed seeders with dependencies

### API Token Management

- [`api-tokens:send-expiration-notifications`](#api-tokenssend-expiration-notifications) - Send token expiration notifications

### Configuration

- [`validate:configuration`](#validateconfiguration) - Validate application configuration

### Environment

- [`restore:local-environment-files`](#restorelocal-environment-files) - Restore .env files from backups

## Command Reference

### `customize:project`

Interactive command to customize the application for your Northwestern project.

**Usage:**

```bash
php artisan customize:project
```

**What it does:**

1. Prompts for application details:
    - Application name
    - Application URL
    - Environment (local, staging, production)
2. Configures database settings
3. Sets up Northwestern integrations
4. Configures super administrator accounts
5. Updates mail configuration

**When to use:**

- First-time project setup
- Resetting configuration to defaults
- Setting up new environments

:::caution
This command modifies configuration files. Run it only during initial setup or when intentionally reconfiguring.
:::

### `db:rebuild`

Completely rebuild the database from scratch.

**Usage:**

```bash
php artisan db:rebuild
```

**What it does:**

1. Clears cache (if possible)
2. Clears queue
3. Runs `migrate:fresh --seed` (drops all tables, runs migrations, runs seeders)
4. Runs `DemoSeeder` if it exists
5. Generates IDE helper files
6. Reports queue size if jobs are pending

**When to use:**

- Resetting development database to clean state
- After pulling major schema changes
- When database is in inconsistent state
- Starting fresh with new seed data

**Options:**

```bash
# Skip confirmation prompt (dangerous in production)
php artisan db:rebuild --force
```

**Example Output:**

```
 WARN  Database rebuild will destroy all data. Do you wish to continue? (yes/no) [no]
 > yes

Dropping all tables...
Migration table created successfully.
Migrating: 2024_01_01_create_users_table
Migrated:  2024_01_01_create_users_table (45.67ms)
...

Seeding: PermissionSeeder
Seeded:  PermissionSeeder (12.34ms)
...

 ✓ Database rebuild complete.

  There are 5 jobs pending in the queue.
  Ensure that your queue worker is running with: php artisan queue:work
```

### `db:wake`

Wake up a paused or sleeping database (for AWS RDS scale-to-zero or similar features).

**Usage:**

```bash
php artisan db:wake
```

**What it does:**

1. Attempts to connect to the database
2. Waits for database to wake up if it's sleeping
3. Confirms connection is established

**When to use:**

- Before running migrations on sleeping database
- Before database-intensive operations
- When getting database connection timeouts

**Example:**

```bash
# Wake database before rebuild
php artisan db:wake && php artisan db:rebuild
```

### `snapshot:create`

Create a snapshot of the current database state.

**Usage:**

```bash
php artisan snapshot:create <name>
```

**Arguments:**

- `name` - Descriptive name for the snapshot (required)

**Examples:**

```bash
# Create snapshot with descriptive name
php artisan snapshot:create clean-test-data

# Create snapshot before major changes
php artisan snapshot:create before-refactor

# Create snapshot with specific test data
php artisan snapshot:create user-story-456
```

**What it does:**

1. Dumps current database to SQL file
2. Saves to `database/snapshots/` directory
3. Names file with timestamp: `name_2025-01-15_10-30-45.sql`

**Output:**

```
Creating snapshot: clean-test-data

 ✓ Snapshot created successfully.

Location: database/snapshots/clean-test-data_2025-01-15_10-30-45.sql
Size: 2.4 MB
```

See [Database Snapshots](/getting-started/06-database-snapshots/) for full details.

### `snapshot:restore`

Restore database from a snapshot.

**Usage:**

```bash
php artisan snapshot:restore <name>
```

**Arguments:**

- `name` - Name of snapshot to restore (without timestamp or extension)

**Examples:**

```bash
# Restore by name
php artisan snapshot:restore clean-test-data

# Restore with force (skip confirmation)
php artisan snapshot:restore clean-test-data --force
```

**What it does:**

1. Finds snapshot file by name
2. Prompts for confirmation (unless `--force`)
3. Drops all tables
4. Imports SQL dump
5. Confirms restoration

:::danger[Destructive Operation]
This will completely replace your current database with the snapshot data. All current data will be lost.
:::

**Output:**

```
 Do you want to restore the snapshot "clean-test-data"? (yes/no) [no]:
 > yes

Restoring snapshot: clean-test-data

 ✓ Snapshot restored successfully.

Restored from: database/snapshots/clean-test-data_2025-01-15_10-30-45.sql
```

### `snapshot:list`

List all available database snapshots.

**Usage:**

```bash
php artisan snapshot:list
```

**What it does:**

Displays table of all snapshots with:

- Snapshot name
- File size
- Creation date
- Database connection

**Example Output:**

```
Database Snapshots

┌─────────────────────────┬──────────┬─────────────────────┬────────────┐
│ Snapshot                │ Size     │ Created             │ Connection │
├─────────────────────────┼──────────┼─────────────────────┼────────────┤
│ clean-test-data         │ 245 KB   │ 2025-01-15 10:30:45 │ mysql      │
│ before-refactor         │ 198 KB   │ 2025-01-14 15:22:10 │ mysql      │
│ user-story-456          │ 312 KB   │ 2025-01-13 09:15:33 │ mysql      │
└─────────────────────────┴──────────┴─────────────────────┴────────────┘
```

### `seeders:list`

List all seeders marked with `#[AutoSeed]` attribute and their dependencies.

**Usage:**

```bash
php artisan seeders:list
```

**What it does:**

1. Discovers all seeders with `#[AutoSeed]` attribute
2. Resolves dependencies
3. Shows execution order
4. Displays dependency graph

**Example Output:**

```
System Seeders (Auto-Discovered)

┌──────────────────────────────┬─────────────────────────────────┐
│ Seeder                       │ Dependencies                    │
├──────────────────────────────┼─────────────────────────────────┤
│ PermissionSeeder             │ None                            │
│ RoleTypeSeeder               │ None                            │
│ RoleSeeder                   │ PermissionSeeder, RoleTypeSeeder│
│ StakeholderSeeder            │ RoleSeeder                      │
└──────────────────────────────┴─────────────────────────────────┘

Execution Order:
  1. PermissionSeeder
  2. RoleTypeSeeder
  3. RoleSeeder
  4. StakeholderSeeder
```

**When to use:**

- Understanding seeder dependencies
- Debugging seeder execution order
- Documenting seeding process

### `api-tokens:send-expiration-notifications`

Send automated notifications for expiring API tokens.

**Usage:**

```bash
php artisan api-tokens:send-expiration-notifications
```

**What it does:**

1. Finds tokens expiring in 30 days
2. Finds tokens expiring in 7 days
3. Finds tokens expiring today
4. Sends email notifications to token owners
5. Logs notifications sent

**Notification Schedule:**

- **30 days before expiration** - First warning
- **7 days before expiration** - Final warning
- **Day of expiration** - Expiration notice

**Scheduling:**

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('api-tokens:send-expiration-notifications')
        ->daily()
        ->at('09:00');
}
```

**Example Output:**

```
Checking for expiring tokens...

 ✓ Sent 3 expiration notifications:
   - 1 token expiring in 30 days
   - 1 token expiring in 7 days
   - 1 token expiring today
```

### `validate:configuration`

Validate application configuration across all validators.

**Usage:**

```bash
php artisan validate:configuration
```

**What it does:**

Runs all configuration validators:

- Database connectivity
- Queue configuration
- Filesystem configuration
- Environment variables
- Northwestern API connectivity

**Example Output:**

```
Validating configuration...

Database Configuration
 ✓ Database connection successful
 ✓ Can write to database
 ✓ Can read from database

Queue Configuration
 ✓ Queue connection valid
 ✓ Can dispatch jobs

Filesystem Configuration
 ✓ Public disk configured
 ✓ Can write files
 ✓ Can read files

Environment Variables
 ✓ All required variables present
 ✓ APP_KEY is set
 ✓ APP_URL is configured

Northwestern Integrations
 ✓ Directory Search API accessible
 ⚠ WebSSO API credentials not configured (optional)
 ⚠ Event Hub not configured (optional)

 ✓ Configuration validation complete.
```

**When to use:**

- After initial setup
- Before deployment
- Troubleshooting configuration issues
- Verifying Northwestern API access

### `restore:local-environment-files`

Restore `.env` files from automatic backups.

**Usage:**

```bash
php artisan restore:local-environment-files
```

**What it does:**

1. Lists available `.env` backups from `storage/environment-backups/`
2. Prompts you to select which backup to restore
3. Restores selected backup to `.env`
4. Creates backup of current `.env` before restoring

**When to use:**

- Accidentally modified or deleted `.env` file
- Want to revert to previous configuration
- Need to recover from configuration mistakes

**Example:**

```bash
php artisan restore:local-environment-files
```

```
Available Environment Backups:

  1. .env.backup.2025-01-15_10-30-45
  2. .env.backup.2025-01-14_15-20-10
  3. .env.backup.2025-01-13_09-15-05

Select backup to restore (1-3):
> 1

 ✓ Environment file restored from backup.

Backup created: .env.backup.2025-01-15_11-45-22
Restored from: storage/environment-backups/.env.backup.2025-01-15_10-30-45
```

## Common Command Workflows

### Starting Fresh in Development

```bash
# Wake database (if using scale-to-zero)
php artisan db:wake

# Rebuild database
php artisan db:rebuild

# Create snapshot of clean state
php artisan snapshot:create clean-start

# Start development
```

### Before Making Risky Changes

```bash
# Create snapshot of current state
php artisan snapshot:create before-changes

# Make your changes
# ...

# If something goes wrong:
php artisan snapshot:restore before-changes
```

### Deploying to New Environment

```bash
# Validate configuration
php artisan validate:configuration

# Run migrations
php artisan migrate --force

# Run seeders
php artisan db:seed --force

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Debugging Seeders

```bash
# List seeders and dependencies
php artisan seeders:list

# Run specific seeder
php artisan db:seed --class=PermissionSeeder

# Rebuild with all seeders
php artisan db:rebuild
```

## Creating Custom Commands

Create your own Artisan commands:

```bash
php artisan make:command YourCustomCommand
```

Example command structure:

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class YourCustomCommand extends Command
{
    protected $signature = 'your:command {argument} {--option}';

    protected $description = 'Description of your command';

    public function handle(): int
    {
        $argument = $this->argument('argument');
        $option = $this->option('option');

        $this->info('Command executed successfully');

        return self::SUCCESS;
    }
}
```

## Next Steps

- [Database Management](/database/overview/) - Understanding database structure
- [Seeder System](/architecture/02-idempotent-seeding/) - How seeders work
- [Configuration](/configuration/overview/) - Configuring the application
