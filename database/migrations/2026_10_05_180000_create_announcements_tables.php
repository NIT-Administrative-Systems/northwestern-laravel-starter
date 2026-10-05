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
        Schema::create('announcements', static function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('severity');

            // `everyone`, `targeted` (the roles and affiliations below) or `public` (signed-out visitors too).
            // The audience lives in JSON columns rather than a pivot, so audits record it and deleting a role
            // leaves nothing behind.
            $table->string('audience');
            $table->json('role_ids')->nullable();
            $table->json('affiliations')->nullable();

            // A draft has no published_at. A published announcement shows from starts_at until ends_at.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->boolean('notify_audience')->default(false);
            $table->timestamp('notified_at')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->index(['published_at', 'starts_at', 'ends_at']);
        });

        Schema::create('announcement_dismissals', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id');
            $table->foreignId('user_id')->index();
            $table->timestamp('dismissed_at');

            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): never
    {
        throw new NoRollbackException();
    }
};
