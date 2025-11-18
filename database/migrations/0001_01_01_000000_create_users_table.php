<?php

declare(strict_types=1);

use App\Domains\Core\Exceptions\NoRollback;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('auth_type');
            $table->string('primary_affiliation')->nullable();
            $table->string('username')->unique();
            $table->string('employee_id')->nullable();
            $table->string('hr_employee_id')->nullable();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            // Never used, but necessary to support local authentication
            $table->string('password')->nullable();
            $table->string('phone')->nullable();
            $table->text('description')->nullable();
            $table->json('job_titles')->default(new Expression('(JSON_ARRAY())'));
            $table->json('departments')->default(new Expression('(JSON_ARRAY())'));
            $table->string('timezone')->default('America/Chicago');
            $table->string('wildcard_photo_s3_key')->nullable();
            $table->dateTime('wildcard_photo_last_synced_at')->nullable();
            $table->dateTime('last_directory_sync_at')->nullable();
            $table->boolean('netid_inactive')->nullable();
            $table->datetime('directory_sync_last_failed_at')->nullable();
            $table->datetime('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['auth_type', 'username']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        throw new NoRollback();
    }
};
