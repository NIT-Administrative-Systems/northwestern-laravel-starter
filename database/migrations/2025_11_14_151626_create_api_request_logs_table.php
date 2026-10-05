<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollbackException;
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
            // Who made the request: a person, or a client acting for itself (see ApiPrincipalType).
            $table->string('principal_type')->nullable();
            // Passport identifiers are strings: client IDs are UUIDs, token IDs are 80 characters.
            $table->uuid('oauth_client_id')->nullable();
            $table->string('token_id', 100)->nullable();
            $table->string('grant_type')->nullable();

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
            // MCP requests: the JSON-RPC method, the tool called, and its outcome, which an
            // HTTP 200 response doesn't reveal. Never the tool's arguments or results.
            $table->string('mcp_method')->nullable();
            $table->string('mcp_tool')->nullable();
            $table->string('mcp_outcome')->nullable();

            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['oauth_client_id', 'created_at']);
            $table->index(['created_at', 'user_id', 'status_code']);
            $table->index(['created_at', 'path']);
        });
    }

    public function down(): never
    {
        throw new NoRollbackException();
    }
};
