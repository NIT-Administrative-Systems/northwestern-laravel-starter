<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollback;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_tokens', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');

            $table->datetime('expires_at')->nullable();
            $table->json('allowed_ips')->nullable();

            $table->unsignedBigInteger('usage_count')->default(0);
            $table->datetime('last_used_at')->nullable();
            $table->datetime('expiration_notified_at')->nullable();
            $table->datetime('revoked_at')->nullable();

            $table->foreignId('rotated_from_token_id')->nullable();
            $table->foreignId('rotated_by_user_id')->nullable();

            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix');

            $table->timestamps();

            $table->index(['user_id', 'last_used_at', 'id'], 'user_access_tokens_user_lookup');
            $table->index('expires_at', 'user_access_tokens_expires_at_index');
            $table->index('last_used_at', 'user_access_tokens_last_used_at_index');
        });
    }

    public function down(): void
    {
        throw new NoRollback();
    }
};
