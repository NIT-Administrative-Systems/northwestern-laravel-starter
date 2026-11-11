<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollbackException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a generated full_name column to users and a pg_trgm GIN index on it. A name
 * search with ILIKE '%term%' then uses the index instead of scanning every row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // The expression matches the User model's accessor, so PHP and the database agree.
        //
        // It's nullable because first_name and last_name are.
        //
        // PostgreSQL requires a generated column that can be
        // indeterminate on null inputs to allow null too.
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
