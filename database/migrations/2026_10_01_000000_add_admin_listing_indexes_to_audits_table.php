<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollbackException;
use App\Domains\User\Models\Audit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indexes the two audit queries the administration panel runs on every page load.
 *
 * - `created_at`: the Audit Logs table sorts by it by default. Without the index,
 *   PostgreSQL scans and sorts the whole table to return the first page.
 * - `audits_role_activity_index`: a partial index covering the {@see Audit::roleActivity()}
 *   scope used by the Role Activity table and its stats widget. The predicate must stay
 *   in sync with that scope's event list, or the planner stops using the index. The
 *   included columns let counts, stats, and user searches run as index-only scans.
 *
 * Both indexes are built concurrently so the migration does not block audit writes,
 * which happen on every audited model save. Concurrent builds cannot run inside a
 * transaction.
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
