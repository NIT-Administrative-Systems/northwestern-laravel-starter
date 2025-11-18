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
        Schema::create('user_api_tokens', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');

            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 5);

            $table->datetime('valid_from');
            $table->datetime('valid_to')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->unsignedBigInteger('usage_count')->default(0);

            $table->datetime('last_used_at')->nullable();
            $table->datetime('expiration_notified_at')->nullable();
            $table->datetime('revoked_at')->nullable();
            $table->foreignId('rotated_from_token_id')->nullable();
            $table->foreignId('rotated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_used_at', 'valid_from', 'id'], 'user_api_tokens_user_lookup');
            $table->index('valid_to', 'user_api_tokens_valid_to_index');
            $table->index('last_used_at', 'user_api_tokens_last_used_at_index');
        });
    }

    public function down(): void
    {
        throw new NoRollback();
    }
};
