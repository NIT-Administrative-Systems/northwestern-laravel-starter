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
        Schema::create('api_request_logs', static function (Blueprint $table) {
            $table->id();
            $table->uuid('trace_id');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('access_token_id')->nullable();

            $table->string('method', 10);
            $table->text('path');
            $table->text('route_name')->nullable();
            $table->string('ip_address', 45);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->unsignedBigInteger('request_bytes')->nullable();
            $table->unsignedBigInteger('response_bytes')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('failure_reason')->nullable();

            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['access_token_id', 'created_at']);
            $table->index(['created_at', 'user_id', 'status_code']);
            $table->index(['created_at', 'path']);
        });
    }

    public function down(): never
    {
        throw new NoRollback();
    }
};
