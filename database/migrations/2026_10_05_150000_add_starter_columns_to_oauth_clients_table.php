<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollbackException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The starter's own columns on Passport's OAuth clients table, kept out of
 * Passport's published migration so that it stays as Passport ships it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->string('origin')->default('administrator')->after('provider');
            // The metadata document an MCP client sent instead of registering.
            $table->string('client_metadata_url', 2048)->nullable()->after('origin');
            // OAuth applications' purpose, contact, whether consent
            // is skipped, and the scopes they may request, where
            // null allows every scope, as for service clients.
            $table->text('description')->nullable()->after('name');
            $table->string('contact_email')->nullable()->after('description');
            $table->boolean('first_party')->default(false)->after('origin');
            $table->json('scopes')->nullable()->after('first_party');
            $table->json('allowed_ips')->nullable()->after('origin');
            $table->datetime('secret_expires_at')->nullable()->after('secret');
            $table->datetime('secret_expiration_notified_at')->nullable()->after('secret_expires_at');
            $table->datetime('last_used_at')->nullable()->after('revoked');
            $table->uuid('rotated_from_client_id')->nullable();
            $table->foreignId('rotated_by_user_id')->nullable();

            $table->index('secret_expires_at');
            $table->index('client_metadata_url');
        });
    }

    public function down(): never
    {
        throw new NoRollbackException();
    }
};
