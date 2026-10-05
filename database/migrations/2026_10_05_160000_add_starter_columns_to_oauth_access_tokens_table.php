<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollbackException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The starter's own columns on Passport's access tokens table, kept out of Passport's
 * published migration so that migration stays as Passport ships it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->datetime('last_used_at')->nullable()->after('revoked');
            $table->datetime('expiration_notified_at')->nullable()->after('expires_at');

            $table->index(['user_id', 'revoked']);
            $table->index('expires_at');
        });
    }

    public function down(): never
    {
        throw new NoRollbackException();
    }
};
