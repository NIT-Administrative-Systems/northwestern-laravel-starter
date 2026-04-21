<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollbackException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a stored generated `full_name` column on the users table and a pg_trgm
 * GIN index on it, so that ILIKE '%term%' searches against user names can use
 * an index instead of a sequential scan with per-row concatenation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Expression intentionally matches the PHP accessor on the User model
        // so values are identical regardless of which side computes them.
        // Nullable because the source columns (first_name, last_name) are both
        // nullable; PostgreSQL requires generated columns whose expression may
        // be indeterminate on NULL inputs to permit NULL themselves.
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name')
                ->storedAs("TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, ''))")
                ->nullable();
        });

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement('CREATE INDEX users_full_name_trgm ON users USING GIN (full_name gin_trgm_ops)');
    }

    public function down(): never
    {
        throw new NoRollbackException();
    }
};
