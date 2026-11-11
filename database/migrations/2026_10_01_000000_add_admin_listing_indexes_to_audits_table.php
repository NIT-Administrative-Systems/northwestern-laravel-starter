<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollbackException;
use App\Domains\Core\Models\Audit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indexes the two audit queries the administration panel runs on every page load.
 *
 * The Audit Logs table sorts by created_at.
 *
 * Without an index, PostgreSQL scans and sorts
 * the whole table to return the first page.
 *
 * audits_role_activity_index is a partial index for
 * the Audit::roleActivity() scope, which the Role
 * Activity table and its stats widget both use.
 *
 * Its predicate must match the scope's event list, or the planner stops using it.
 *
 * Its included columns let counts, stats and
 * user searches run as index-only scans.
 *
 * Both are built concurrently, so the migration doesn't block the audit write that
 * every audited save makes. A concurrent build can't run inside a transaction.
 *
 * @see Audit::roleActivity()
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $connection = DB::connection(config('audit.drivers.database.connection', config('database.default')));

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $table = config('audit.drivers.database.table', 'audits');

        $connection->statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$table}_created_at_index ON {$table} (created_at)");

        $connection->statement(<<<SQL
            CREATE INDEX CONCURRENTLY IF NOT EXISTS {$table}_role_activity_index
            ON {$table} (auditable_type, created_at) INCLUDE (event, auditable_id, user_id, impersonator_user_id)
            WHERE event IN ('role_assigned', 'role_removed')
            SQL);
    }

    public function down(): never
    {
        throw new NoRollbackException();
    }
};
