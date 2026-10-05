<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollbackException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person's connection to an OAuth application: recorded when the application first gets a
 * token for them, and removed when they disconnect it. It outlives Passport's token rows,
 * which `passport:purge` deletes, so the first connection date and the authorization survive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->uuid('oauth_client_id');
            $table->json('scopes');
            $table->datetime('connected_at');
            $table->datetime('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'oauth_client_id']);
            $table->index('oauth_client_id');
        });
    }

    public function down(): never
    {
        throw new NoRollbackException();
    }
};
