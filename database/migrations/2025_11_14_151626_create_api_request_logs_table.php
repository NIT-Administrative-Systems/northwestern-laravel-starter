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
        Schema::create('user_api_token_request_logs', static function (Blueprint $table) {
            $table->id();
            $table->uuid('trace_id');
            $table->foreignId('user_id');
            $table->foreignId('user_api_token_id')->nullable();

            $table->string('method', 10);
            $table->text('path');
            $table->text('route_name')->nullable();
            $table->string('ip_address', 45);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->unsignedBigInteger('response_bytes')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('failure_reason')->nullable();

            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['user_api_token_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        throw new NoRollback();
    }
};
